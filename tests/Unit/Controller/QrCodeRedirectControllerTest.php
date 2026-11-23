<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Controller;

use Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Website\QrCodeRedirectController;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Event\QrCodeScannedEvent;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\BotDetector;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\ScanRecorderInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\FakeLinkProvider;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\InMemoryQrCodeRepository;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class QrCodeRedirectControllerTest extends TestCase
{
    use SuluStubsTrait;

    private const PAGE = 'page-uuid';
    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private InMemoryQrCodeRepository $repository;

    private EventDispatcher $eventDispatcher;

    /**
     * @var list<string>
     */
    private array $recordedScans = [];

    private bool $recorderFails = false;

    protected function setUp(): void
    {
        $this->repository = new InMemoryQrCodeRepository();
        $this->eventDispatcher = new EventDispatcher();
        $this->recordedScans = [];
        $this->recorderFails = false;

        $qrCode = new QrCode('abc234', 'Market', 'page', self::PAGE);
        $qrCode->setLink('page', self::PAGE, 'fr');
        $this->repository->add($qrCode);
    }

    public function testRedirectsTemporarilyAndIsNeverCached(): void
    {
        $response = $this->scan('abc234');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), 'A 301 would freeze the target in browsers.');
        self::assertSame('https://www.example.com/fr/marche', $response->headers->get('Location'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertCount(1, $this->recordedScans);
    }

    public function testCodeLookupIsCaseInsensitive(): void
    {
        self::assertSame(Response::HTTP_FOUND, $this->scan('ABC234')->getStatusCode());
    }

    public function testFollowsTheLanguageOfThePhone(): void
    {
        $response = $this->scan('abc234', ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9,fr;q=0.5']);

        self::assertSame('https://www.example.com/en/market', $response->headers->get('Location'));
    }

    public function testUnknownCodeIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->scan('zzzzzz');
    }

    public function testRobotsAreRedirectedButNotCounted(): void
    {
        $response = $this->scan('abc234', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame([], $this->recordedScans);
    }

    public function testRobotsAreCountedWhenTheFilterIsDisabled(): void
    {
        $this->scan('abc234', ['HTTP_USER_AGENT' => 'curl/8.7.1'], ignoreBots: false);

        self::assertCount(1, $this->recordedScans);
    }

    public function testHeadRequestsAreNotCounted(): void
    {
        $response = $this->scan('abc234', method: 'HEAD');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame([], $this->recordedScans);
    }

    public function testAFailingStatisticsStoreNeverBreaksTheRedirection(): void
    {
        $this->recorderFails = true;
        $counted = null;
        $this->eventDispatcher->addListener(QrCodeScannedEvent::class, static function (QrCodeScannedEvent $event) use (&$counted): void {
            $counted = $event->isCounted();
        });

        $response = $this->scan('abc234');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertFalse($counted);
    }

    public function testUnavailableTargetGoesToTheFallbackUrlAndIsStillCounted(): void
    {
        $response = $this->scan('abc234', targetExists: false, fallbackUrl: 'https://www.example.com/');

        self::assertSame('https://www.example.com/', $response->headers->get('Location'));
        self::assertCount(1, $this->recordedScans, 'Someone did scan the code.');
    }

    public function testUnavailableTargetWithoutFallbackIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->scan('abc234', targetExists: false);
    }

    public function testListenersSeeTheScanAndMayChangeTheDestination(): void
    {
        $seen = null;
        $this->eventDispatcher->addListener(QrCodeScannedEvent::class, static function (QrCodeScannedEvent $event) use (&$seen): void {
            $seen = [$event->getQrCode()->getCode(), $event->getTargetUrl(), $event->isCounted(), $event->getRequest()->getPathInfo()];
            $event->setTargetUrl('https://campaign.example.com/landing');
        });

        $response = $this->scan('abc234');

        self::assertSame(['abc234', '/fr/marche', true, '/qr/abc234'], $seen);
        self::assertSame('https://campaign.example.com/landing', $response->headers->get('Location'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsafeTargets(): iterable
    {
        yield 'script' => ['javascript:alert(document.cookie)'];
        yield 'data url' => ['data:text/html,<script>alert(1)</script>'];
        yield 'protocol-relative' => ['//evil.example/phishing'];
        yield 'backslash trick' => ['/\\evil.example/phishing'];
        yield 'header injection' => ["https://example.org/\r\nSet-Cookie: session=stolen"];
        yield 'line feed only' => ["https://example.org/\nX-Injected: 1"];
        yield 'space' => ['https://example.org/a b'];
        yield 'null byte' => ["https://example.org/\0"];
        yield 'no host' => ['https:///path'];
        yield 'other scheme' => ['ftp://example.org/file'];
        yield 'empty' => [''];
    }

    #[DataProvider('provideUnsafeTargets')]
    public function testNeverRedirectsToAnUnsafeTargetWhateverItsOrigin(string $unsafeUrl): void
    {
        // Simulates anything that could produce the URL: a link provider, a listener, an old database row.
        $this->eventDispatcher->addListener(QrCodeScannedEvent::class, static function (QrCodeScannedEvent $event) use ($unsafeUrl): void {
            $event->setTargetUrl($unsafeUrl);
        });

        $response = $this->scan('abc234', fallbackUrl: '/qr-code-indisponible');

        self::assertSame('https://www.example.com/qr-code-indisponible', $response->headers->get('Location'));
    }

    public function testAnUnsafeTargetWithoutFallbackIsNotFoundRatherThanAnError(): void
    {
        // Written straight to the database, bypassing the validation of the administration.
        $qrCode = new QrCode('bad234', 'Tampered', 'external', "https://tickets.example.org/buy\r\nX-Injected: 1");
        $this->repository->add($qrCode);

        $this->expectException(NotFoundHttpException::class);

        $this->scan('bad234');
    }

    public function testAnUnsafeFallbackIsNotUsedEither(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->scan('abc234', targetExists: false, fallbackUrl: 'javascript:alert(1)');
    }

    public function testMailAndPhoneTargetsAreAllowed(): void
    {
        $this->eventDispatcher->addListener(QrCodeScannedEvent::class, static function (QrCodeScannedEvent $event): void {
            $event->setTargetUrl('tel:+33490000000');
        });

        self::assertSame('tel:+33490000000', $this->scan('abc234')->headers->get('Location'));
    }

    public function testExternalTargetsAreLeftUntouched(): void
    {
        $qrCode = new QrCode('ext234', 'Tickets', 'external', 'https://tickets.example.org/buy');
        $this->repository->add($qrCode);

        $response = $this->scan('ext234');

        self::assertSame('https://tickets.example.org/buy', $response->headers->get('Location'));
    }

    /**
     * @param array<string, string> $server
     */
    private function scan(
        string $code,
        array $server = [],
        string $method = 'GET',
        bool $targetExists = true,
        ?string $fallbackUrl = null,
        bool $ignoreBots = true,
    ): Response {
        $pages = $targetExists
            ? ['fr' => [self::PAGE => '/fr/marche'], 'en' => [self::PAGE => '/en/market']]
            : [];

        $recorder = $this->createStub(ScanRecorderInterface::class);
        $recorder->method('record')->willReturnCallback(function (string $qrCodeId): void {
            if ($this->recorderFails) {
                throw new \RuntimeException('Database is down.');
            }

            $this->recordedScans[] = $qrCodeId;
        });

        $controller = new QrCodeRedirectController(
            $this->repository,
            new TargetUrlResolver(
                TestFactory::linkProviderPool([
                    'page' => new FakeLinkProvider($pages),
                    'external' => new FakeLinkProvider(['fr' => [
                        'https://tickets.example.org/buy' => 'https://tickets.example.org/buy',
                        "https://tickets.example.org/buy\r\nX-Injected: 1" => "https://tickets.example.org/buy\r\nX-Injected: 1",
                    ]]),
                ]),
                $this->createWebspaceManager(['fr', 'en']),
            ),
            $recorder,
            new BotDetector(),
            $this->eventDispatcher,
            $fallbackUrl,
            $ignoreBots,
        );

        $request = Request::create(
            'https://www.example.com/qr/' . $code,
            $method,
            server: ['HTTP_USER_AGENT' => self::PHONE, 'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9', ...$server],
        );

        return $controller($request, $code);
    }
}
