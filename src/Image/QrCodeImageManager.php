<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Image;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\UnsupportedImageSizeException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\QrCodeRendererInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\RenderOptions;
use Islesurlasorguetourisme\SuluQrCodeBundle\Storage\QrCodeStorageInterface;

/**
 * Returns the image of a QR code, rendering and storing it on first use.
 *
 * Stored files are named after a fingerprint of everything that influences the
 * pixels (public URL, options, logo version, renderer version). A change of any
 * of them yields a new file name, so a stale image can never be served.
 *
 * Files are written while answering GET requests, so their number is bounded on
 * purpose: a fixed list of sizes, and a single fingerprint kept per QR code.
 */
final class QrCodeImageManager
{
    /**
     * Bump to invalidate every stored image after a change in the rendering code.
     */
    private const RENDER_VERSION = 2;

    /**
     * Widths, in pixels, of the previews shown in the administration.
     */
    public const THUMBNAIL_SIZE = 80;
    public const PREVIEW_SIZE = 160;
    public const LARGE_THUMBNAIL_SIZE = 480;

    /**
     * The only sizes served besides the one configured on the QR code. Anything else is
     * refused: each distinct size is a file on disk, and the size comes from the URL.
     */
    public const PREVIEW_SIZES = [self::THUMBNAIL_SIZE, self::PREVIEW_SIZE, self::LARGE_THUMBNAIL_SIZE];

    /**
     * Name of the file remembering which fingerprint the stored images belong to.
     */
    private const FINGERPRINT_FILE = 'fingerprint';

    public function __construct(
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly QrCodeRendererInterface $renderer,
        private readonly QrCodeStorageInterface $storage,
        private readonly LogoResolver $logoResolver,
    ) {
    }

    /**
     * @param int|null $size Width of the image in pixels: one of {@see self::PREVIEW_SIZES},
     *                       or null for the size configured on the QR code
     *
     * @throws UnsupportedImageSizeException
     */
    public function getImage(QrCode $qrCode, ImageFormat $format, ?int $size = null): QrCodeImage
    {
        $size ??= $qrCode->getSize();

        if ($size !== $qrCode->getSize() && !\in_array($size, self::PREVIEW_SIZES, true)) {
            throw new UnsupportedImageSizeException($size, [...self::PREVIEW_SIZES, $qrCode->getSize()]);
        }

        $id = $qrCode->getId();
        $fingerprint = $this->getFingerprint($qrCode);

        // Images of a previous appearance, logo or base URL will never be asked for again:
        // drop them so that the storage cannot grow with every change (or with every host
        // name the administration is reached through when no base URL is configured).
        if ($this->storage->read($id, self::FINGERPRINT_FILE) !== $fingerprint) {
            $this->storage->purge($id);
            $this->storage->write($id, self::FINGERPRINT_FILE, $fingerprint);
        }

        // The fingerprint stays in the file name: should two requests with different
        // fingerprints overlap, neither can ever be served the image of the other.
        $fileName = \sprintf('%s-%d.%s', $fingerprint, $size, $format->value);
        $content = $this->storage->read($id, $fileName);

        if (null === $content) {
            $content = $this->render($qrCode, $format, $size);
            $this->storage->write($id, $fileName, $content);
        }

        return new QrCodeImage($content, $format, $fingerprint);
    }

    /**
     * Cheap to compute: nothing is rendered. Useful to build cache-busting URLs.
     */
    public function getFingerprint(QrCode $qrCode): string
    {
        $payload = \json_encode([
            self::RENDER_VERSION,
            $this->publicUrlGenerator->generate($qrCode->getCode()),
            $qrCode->getForegroundColor(),
            $qrCode->getBackgroundColor(),
            $qrCode->isTransparentBackground(),
            $qrCode->getSize(),
            $qrCode->getMargin(),
            $qrCode->getErrorCorrectionLevel(),
            $this->logoResolver->getVersionKey($qrCode),
            $qrCode->getLogoSize(),
        ], \JSON_THROW_ON_ERROR);

        return \substr(\sha1($payload), 0, 16);
    }

    /**
     * Drops the stored files of a QR code. To call when it is modified or removed.
     */
    public function purge(QrCode $qrCode): void
    {
        $this->storage->purge($qrCode->getId());
    }

    private function render(QrCode $qrCode, ImageFormat $format, int $size): string
    {
        $logoPath = $this->logoResolver->createLocalCopy($qrCode);

        try {
            $options = (new RenderOptions(
                $qrCode->getForegroundColor(),
                $qrCode->getBackgroundColor(),
                $qrCode->isTransparentBackground(),
                $qrCode->getSize(),
                $qrCode->getMargin(),
                $qrCode->getErrorCorrectionLevel(),
                $logoPath,
                $qrCode->getLogoSize(),
            ))->withSize($size);

            return $this->renderer->render(
                $this->publicUrlGenerator->generate($qrCode->getCode()),
                $options,
                $format,
            );
        } finally {
            if (null !== $logoPath) {
                @\unlink($logoPath);
            }
        }
    }
}
