<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Renderer;

use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\RenderOptions;
use PHPUnit\Framework\TestCase;

final class RenderOptionsTest extends TestCase
{
    public function testDefaultsAreBlackOnTransparent(): void
    {
        $options = new RenderOptions();

        self::assertSame('#000000', $options->foregroundColor);
        self::assertTrue($options->transparentBackground);
        self::assertSame('M', $options->effectiveErrorCorrectionLevel());
    }

    public function testALogoForcesTheHighestErrorCorrectionLevel(): void
    {
        $options = (new RenderOptions(errorCorrectionLevel: 'L'))->withLogoPath('/tmp/logo.png');

        self::assertSame('L', $options->errorCorrectionLevel);
        self::assertSame('H', $options->effectiveErrorCorrectionLevel());
    }

    public function testWithSizeScalesTheMarginProportionally(): void
    {
        $thumbnail = (new RenderOptions(size: 1000, margin: 40))->withSize(80);

        self::assertSame(80, $thumbnail->size);
        self::assertSame(3, $thumbnail->margin);
    }

    public function testWithSameSizeReturnsTheSameInstance(): void
    {
        $options = new RenderOptions(size: 600);

        self::assertSame($options, $options->withSize(600));
    }
}
