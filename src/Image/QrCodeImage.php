<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Image;

use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;

/**
 * A rendered QR code image.
 */
final class QrCodeImage
{
    /**
     * @param string $fingerprint Changes whenever the pixels of the image change
     */
    public function __construct(
        public readonly string $content,
        public readonly ImageFormat $format,
        public readonly string $fingerprint,
    ) {
    }
}
