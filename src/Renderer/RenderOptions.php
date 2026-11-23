<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Renderer;

/**
 * Everything that influences the pixels of a rendered QR code, apart from its content.
 */
final class RenderOptions
{
    /**
     * @param int $size Total width of the image in pixels, margin included
     * @param int $margin Quiet zone around the code, in pixels
     * @param string|null $logoPath Local path of a raster image drawn in the centre
     * @param int $logoSize Logo width in percent of the image width
     */
    public function __construct(
        public readonly string $foregroundColor = '#000000',
        public readonly string $backgroundColor = '#ffffff',
        public readonly bool $transparentBackground = true,
        public readonly int $size = 1000,
        public readonly int $margin = 40,
        public readonly string $errorCorrectionLevel = 'M',
        public readonly ?string $logoPath = null,
        public readonly int $logoSize = 20,
    ) {
    }

    /**
     * A logo hides modules in the middle of the code: the highest error correction
     * level is then required for the code to remain readable.
     */
    public function effectiveErrorCorrectionLevel(): string
    {
        return null !== $this->logoPath ? 'H' : $this->errorCorrectionLevel;
    }

    /**
     * The same options for an image of another width, with margin scaled accordingly.
     */
    public function withSize(int $size): self
    {
        if ($size === $this->size) {
            return $this;
        }

        return new self(
            $this->foregroundColor,
            $this->backgroundColor,
            $this->transparentBackground,
            $size,
            (int) \round($this->margin * $size / \max(1, $this->size)),
            $this->errorCorrectionLevel,
            $this->logoPath,
            $this->logoSize,
        );
    }

    public function withLogoPath(?string $logoPath): self
    {
        return new self(
            $this->foregroundColor,
            $this->backgroundColor,
            $this->transparentBackground,
            $this->size,
            $this->margin,
            $this->errorCorrectionLevel,
            $logoPath,
            $this->logoSize,
        );
    }
}
