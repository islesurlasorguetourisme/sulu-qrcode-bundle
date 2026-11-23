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
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;

/**
 * Gives access to the logo of a QR code, stored in the Sulu media library.
 *
 * A media file is untrusted input. It is never handed to the renderer as is:
 * it is checked, decoded and re-encoded first (see {@see LogoResolver::normalize()}).
 */
final class LogoResolver
{
    /**
     * Raster formats GD can read. Vector logos (SVG) cannot be drawn into a PNG.
     */
    public const SUPPORTED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * Largest media file accepted as a logo, in bytes.
     */
    public const MAX_FILE_SIZE = 5_000_000;

    /**
     * Limits checked on the image header, before any pixel is decoded: a tiny file can
     * declare gigantic dimensions and exhaust the memory when decoded.
     */
    private const MAX_SIDE = 4096;
    private const MAX_PIXELS = 8_000_000;

    /**
     * Longest side of the copy handed to the renderer. A logo covers at most 30% of the
     * largest image (4000 px), so nothing is lost.
     */
    private const NORMALIZED_SIDE = 1200;

    public function __construct(
        private readonly StorageInterface $mediaStorage,
    ) {
    }

    public function isSupported(MediaInterface $media): bool
    {
        return null !== $this->getUsableFileVersion($media);
    }

    /**
     * Identifies the exact file used as logo, so that caches are invalidated when the media is replaced.
     * Returns null when the QR code has no usable logo.
     */
    public function getVersionKey(QrCode $qrCode): ?string
    {
        $media = $qrCode->getLogo();
        $fileVersion = null !== $media ? $this->getUsableFileVersion($media) : null;

        if (null === $media || null === $fileVersion) {
            return null;
        }

        return $media->getId() . '-' . $fileVersion->getVersion();
    }

    /**
     * Writes a sanitised PNG copy of the logo to a temporary local file and returns its path,
     * or null when there is no usable logo. The caller is responsible for deleting the file.
     */
    public function createLocalCopy(QrCode $qrCode): ?string
    {
        $media = $qrCode->getLogo();
        $fileVersion = null !== $media ? $this->getUsableFileVersion($media) : null;

        if (null === $fileVersion) {
            return null;
        }

        try {
            $source = $this->mediaStorage->load($fileVersion->getStorageOptions());
            // One byte more than allowed is enough to know the file is too big.
            $content = \stream_get_contents($source, self::MAX_FILE_SIZE + 1);

            if (\is_resource($source)) {
                \fclose($source);
            }

            $png = false !== $content ? $this->normalize($content) : null;
            $path = null !== $png ? \tempnam(\sys_get_temp_dir(), 'sulu_qr_logo_') : false;

            if (null === $png || false === $path) {
                return null;
            }

            if (false === \file_put_contents($path, $png)) {
                @\unlink($path);

                return null;
            }

            return $path;
        } catch (\Throwable) {
            // A missing or unreadable media file must not prevent the QR code from being rendered.
            return null;
        }
    }

    /**
     * Turns an untrusted image into a PNG of reasonable size, or returns null when it is not acceptable.
     *
     * Re-encoding drops everything that is not pixels: metadata (EXIF, GPS position, author),
     * trailing data and anything hidden in a polyglot file. It also guarantees that the original
     * bytes of the media never leave the application through a downloaded QR code.
     */
    private function normalize(string $content): ?string
    {
        if (\strlen($content) > self::MAX_FILE_SIZE) {
            return null;
        }

        $info = @\getimagesizefromstring($content);
        if (false === $info) {
            return null;
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $image = @\imagecreatefromstring($content);
        if (false === $image) {
            return null;
        }

        // Keep the transparency of the logo.
        \imagepalettetotruecolor($image);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);

        $longestSide = \max($width, $height);
        if ($longestSide > self::NORMALIZED_SIDE) {
            $ratio = self::NORMALIZED_SIDE / $longestSide;
            $scaledWidth = \max(1, (int) \round($width * $ratio));
            $scaledHeight = \max(1, (int) \round($height * $ratio));

            // imagecopyresampled() rather than imagescale(): the latter fails with
            // some interpolation modes depending on how GD was built.
            $scaled = \imagecreatetruecolor($scaledWidth, $scaledHeight);
            if (false === $scaled) {
                return null;
            }

            \imagealphablending($scaled, false);
            \imagesavealpha($scaled, true);

            \imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);

            $image = $scaled;
        }

        \ob_start();
        $written = \imagepng($image);
        $png = \ob_get_clean();

        return $written && \is_string($png) && '' !== $png ? $png : null;
    }

    private function getUsableFileVersion(MediaInterface $media): ?FileVersion
    {
        foreach ($media->getFiles() as $file) {
            $fileVersion = $file->getLatestFileVersion();

            if (
                null !== $fileVersion
                && \in_array($fileVersion->getMimeType(), self::SUPPORTED_MIME_TYPES, true)
                && $fileVersion->getSize() <= self::MAX_FILE_SIZE
            ) {
                return $fileVersion;
            }
        }

        return null;
    }
}
