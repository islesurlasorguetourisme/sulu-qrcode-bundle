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
 * Helpers around "#rrggbb" colours, the format produced by Sulu's colour picker.
 */
final class HexColor
{
    private function __construct()
    {
    }

    /**
     * Returns the colour as lower case "#rrggbb", or null when it is not a valid hexadecimal colour.
     * The short "#rgb" notation is expanded.
     */
    public static function normalize(string $color): ?string
    {
        $color = \strtolower(\trim($color));

        if (1 === \preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $matches)) {
            return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
        }

        return 1 === \preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : null;
    }

    /**
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    public static function toRgb(string $color): array
    {
        $normalized = self::normalize($color);

        if (null === $normalized) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid hexadecimal colour.', $color));
        }

        /** @var array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb */
        $rgb = [
            (int) \hexdec(\substr($normalized, 1, 2)),
            (int) \hexdec(\substr($normalized, 3, 2)),
            (int) \hexdec(\substr($normalized, 5, 2)),
        ];

        return $rgb;
    }

    /**
     * WCAG contrast ratio between two colours, from 1 (identical) to 21 (black on white).
     */
    public static function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = self::relativeLuminance($first);
        $secondLuminance = self::relativeLuminance($second);

        return (\max($firstLuminance, $secondLuminance) + 0.05) / (\min($firstLuminance, $secondLuminance) + 0.05);
    }

    private static function relativeLuminance(string $color): float
    {
        $channels = \array_map(
            static function (int $channel): float {
                $value = $channel / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            self::toRgb($color),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
