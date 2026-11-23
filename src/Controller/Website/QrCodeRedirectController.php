<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Website;

use Islesurlasorguetourisme\SuluQrCodeBundle\Event\QrCodeScannedEvent;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\ShortCodeGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepositoryInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\BotDetector;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\ScanRecorderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Public endpoint behind every printed QR code: counts the scan, then redirects to the current target.
 */
final class QrCodeRedirectController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly QrCodeRepositoryInterface $repository,
        private readonly TargetUrlResolver $targetUrlResolver,
        private readonly ScanRecorderInterface $scanRecorder,
        private readonly BotDetector $botDetector,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?string $fallbackUrl = null,
        private readonly bool $ignoreBots = true,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(Request $request, string $code): Response
    {
        $qrCode = $this->repository->findOneByCode(ShortCodeGenerator::normalize($code));

        if (null === $qrCode) {
            throw new NotFoundHttpException('Unknown QR code.');
        }

        try {
            $targetUrl = $this->targetUrlResolver->resolve($qrCode, $request->getLanguages());
        } catch (\Throwable $exception) {
            // A broken target must degrade to the fallback, never to an error page on someone's phone.
            $this->logger->error('Unable to resolve the target of QR code "{code}": {message}', [
                'code' => $qrCode->getCode(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            $targetUrl = null;
        }

        // HEAD requests are probes, not visits.
        $counted = $request->isMethod(Request::METHOD_GET) && !($this->ignoreBots && $this->botDetector->isBot($request));

        if ($counted) {
            try {
                $this->scanRecorder->record($qrCode->getId(), new \DateTimeImmutable());
            } catch (\Throwable $exception) {
                // Statistics are secondary: the redirection must happen even if they cannot be written.
                $this->logger->error('Unable to record the scan of QR code "{code}": {message}', [
                    'code' => $qrCode->getCode(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
                $counted = false;
            }
        }

        $event = new QrCodeScannedEvent($qrCode, $request, $targetUrl, $counted);
        $this->eventDispatcher->dispatch($event);

        // Last line of defence, whatever produced the URL (stored link, link provider, listener):
        // nothing but a clean web address may reach the "Location" header.
        $targetUrl = $event->getTargetUrl();
        if (null !== $targetUrl && !self::isSafeTarget($targetUrl)) {
            $this->logger->warning('The target of QR code "{code}" was discarded because it is not a valid redirection URL.', [
                'code' => $qrCode->getCode(),
            ]);
            $targetUrl = null;
        }

        $targetUrl ??= $this->fallbackUrl;

        if (null === $targetUrl || !self::isSafeTarget($targetUrl)) {
            throw new NotFoundHttpException('The target of this QR code is not available.');
        }

        // Sulu returns a path for pages of the current host; make it absolute for strict HTTP clients.
        if (\str_starts_with($targetUrl, '/') && !\str_starts_with($targetUrl, '//')) {
            $targetUrl = $request->getSchemeAndHttpHost() . $targetUrl;
        }

        // 302 and no caching on purpose: a cached or permanent redirection would freeze
        // the target in browsers and proxies, and hide later scans from the statistics.
        $response = new RedirectResponse($targetUrl, Response::HTTP_FOUND);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * Accepts absolute http(s) URLs, "mailto:" and "tel:" links, and paths of the current host.
     * Rejects control characters and white space (header injection), other schemes
     * ("javascript:", "data:"…) and protocol-relative URLs ("//host", "/\\host").
     */
    private static function isSafeTarget(string $url): bool
    {
        if ('' === $url || 1 === \preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }

        return 1 === \preg_match('#^(https?://[^/?\#]+|mailto:.+|tel:.+|/(?![/\\\\]))#i', $url);
    }
}
