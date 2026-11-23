<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Renderer;

enum ImageFormat: string
{
    case PNG = 'png';
    case SVG = 'svg';

    public function mimeType(): string
    {
        return match ($this) {
            self::PNG => 'image/png',
            self::SVG => 'image/svg+xml',
        };
    }
}
