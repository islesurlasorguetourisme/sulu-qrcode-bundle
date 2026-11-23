<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Generator;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the absolute URL encoded into the image of a QR code.
 *
 * This URL ends up printed on paper: it must stay stable for the whole life of
 * the QR code, which is why the host can (and in production should) be pinned
 * with the "sulu_qr_code.base_url" option instead of being taken from the request.
 */
final class PublicUrlGenerator
{
    public const ROUTE_NAME = 'sulu_qr_code.redirect';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $baseUrl = null,
    ) {
    }

    public function generate(string $code): string
    {
        try {
            if (null !== $this->baseUrl) {
                return $this->baseUrl . $this->generatePath($code);
            }

            return $this->urlGenerator->generate(self::ROUTE_NAME, ['code' => $code], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException $exception) {
            throw new \LogicException(\sprintf('The route "%s" is not available in this context. Import "@SuluQrCodeBundle/config/routing_website.yaml" in both your admin and website routing files (see the bundle README).', self::ROUTE_NAME), 0, $exception);
        }
    }

    /**
     * Path of the public URL, without scheme and host.
     */
    public function generatePath(string $code): string
    {
        $context = $this->urlGenerator->getContext();
        $path = $this->urlGenerator->generate(self::ROUTE_NAME, ['code' => $code], UrlGeneratorInterface::ABSOLUTE_PATH);

        // The pinned base URL replaces the current front controller prefix (e.g. "/index.php"), if any.
        $prefix = $context->getBaseUrl();
        if ('' !== $prefix && \str_starts_with($path, $prefix)) {
            $path = \substr($path, \strlen($prefix));
        }

        return $path;
    }
}
