<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Renderer;

interface QrCodeRendererInterface
{
    /**
     * Renders the given content as a QR code and returns the binary (PNG) or textual (SVG) image.
     */
    public function render(string $content, RenderOptions $options, ImageFormat $format): string;
}
