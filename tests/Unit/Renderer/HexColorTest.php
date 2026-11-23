<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Renderer;

use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\HexColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HexColorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideColors(): iterable
    {
        yield 'lower case' => ['#0b3d91', '#0b3d91'];
        yield 'upper case' => ['#FFF4D6', '#fff4d6'];
        yield 'short notation' => ['#F0a', '#ff00aa'];
        yield 'surrounding spaces' => [' #000000 ', '#000000'];
        yield 'missing hash' => ['000000', null];
        yield 'named colour' => ['red', null];
        yield 'too long' => ['#0000000', null];
        yield 'css injection' => ['#000;}', null];
    }

    #[DataProvider('provideColors')]
    public function testNormalize(string $color, ?string $expected): void
    {
        self::assertSame($expected, HexColor::normalize($color));
    }

    public function testToRgb(): void
    {
        self::assertSame([11, 61, 145], HexColor::toRgb('#0b3d91'));
        self::assertSame([255, 255, 255], HexColor::toRgb('#fff'));
    }

    public function testToRgbRejectsInvalidColours(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexColor::toRgb('blue');
    }

    public function testContrastRatio(): void
    {
        self::assertEqualsWithDelta(21.0, HexColor::contrastRatio('#000000', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(1.0, HexColor::contrastRatio('#336699', '#336699'), 0.001);
        // The order of the arguments does not matter.
        self::assertEqualsWithDelta(
            HexColor::contrastRatio('#0b3d91', '#fff4d6'),
            HexColor::contrastRatio('#fff4d6', '#0b3d91'),
            0.0001,
        );
    }
}
