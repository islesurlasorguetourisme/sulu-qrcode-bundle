<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Renderer;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Renderer backed by endroid/qr-code.
 */
final class EndroidQrCodeRenderer implements QrCodeRendererInterface
{
    private const TRANSPARENT_ALPHA = 127;

    /**
     * By default endroid reduces the PNG to a small palette, which shifts colours slightly:
     * not acceptable when an editor types the exact colour of a brand. Keep true colours.
     * (Literal key: the constant only exists as PngWriter::WRITER_OPTION_NUMBER_OF_COLORS.)
     */
    private const PNG_WRITER_OPTIONS = ['number_of_colors' => null];

    public function render(string $content, RenderOptions $options, ImageFormat $format): string
    {
        // endroid's "size" is the code without its margin, ours is the whole image.
        $margin = \max(0, $options->margin);
        $innerSize = \max(1, $options->size - 2 * $margin);
        $hasLogo = null !== $options->logoPath;

        [$red, $green, $blue] = HexColor::toRgb($options->foregroundColor);
        [$backgroundRed, $backgroundGreen, $backgroundBlue] = HexColor::toRgb($options->backgroundColor);

        $builder = new Builder(
            writer: ImageFormat::SVG === $format ? new SvgWriter() : new PngWriter(),
            writerOptions: ImageFormat::SVG === $format ? [] : self::PNG_WRITER_OPTIONS,
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->errorCorrectionLevel($options->effectiveErrorCorrectionLevel()),
            size: $innerSize,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color($red, $green, $blue),
            backgroundColor: new Color(
                $backgroundRed,
                $backgroundGreen,
                $backgroundBlue,
                $options->transparentBackground ? self::TRANSPARENT_ALPHA : 0,
            ),
            logoPath: $options->logoPath ?? '',
            logoResizeToWidth: $hasLogo ? \max(1, (int) \round($innerSize * $options->logoSize / 100)) : null,
            // Clears the modules behind the logo. The SVG writer cannot do it.
            logoPunchoutBackground: $hasLogo && ImageFormat::PNG === $format,
        );

        return $builder->build()->getString();
    }

    private function errorCorrectionLevel(string $level): ErrorCorrectionLevel
    {
        return match (\strtoupper($level)) {
            'L' => ErrorCorrectionLevel::Low,
            'Q' => ErrorCorrectionLevel::Quartile,
            'H' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };
    }
}
