<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Renderer;

use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\EndroidQrCodeRenderer;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\RenderOptions;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
final class EndroidQrCodeRendererTest extends TestCase
{
    private const URL = 'https://www.example.com/qr/k7qm2xw9pb';

    private ?string $logoPath = null;

    protected function tearDown(): void
    {
        if (null !== $this->logoPath) {
            @\unlink($this->logoPath);
        }
    }

    public function testPngHasExactlyTheRequestedSizeMarginIncluded(): void
    {
        $png = (new EndroidQrCodeRenderer())->render(self::URL, new RenderOptions(size: 600, margin: 30), ImageFormat::PNG);

        $info = \getimagesizefromstring($png);
        self::assertIsArray($info);
        self::assertSame([600, 600], [$info[0], $info[1]]);
        self::assertSame('image/png', $info['mime']);
    }

    public function testDefaultIsBlackOnTransparent(): void
    {
        $image = $this->decode((new EndroidQrCodeRenderer())->render(self::URL, new RenderOptions(), ImageFormat::PNG));

        self::assertSame(127, $this->pixel($image, 0, 0)['alpha'], 'The margin must be fully transparent.');

        $darkPixel = $this->findOpaquePixel($image);
        self::assertSame([0, 0, 0], [$darkPixel['red'], $darkPixel['green'], $darkPixel['blue']]);
    }

    public function testColoursAreRenderedExactlyWithoutPaletteApproximation(): void
    {
        $options = new RenderOptions('#0b3d91', '#fff4d6', false, 400, 20);
        $image = $this->decode((new EndroidQrCodeRenderer())->render(self::URL, $options, ImageFormat::PNG));

        $background = $this->pixel($image, 0, 0);
        self::assertSame([255, 244, 214, 0], [$background['red'], $background['green'], $background['blue'], $background['alpha']]);

        // The top-left finder pattern starts right after the margin.
        $module = $this->pixel($image, 26, 26);
        self::assertSame([11, 61, 145], [$module['red'], $module['green'], $module['blue']]);
    }

    public function testSvgIsWellFormedAndCarriesTheColours(): void
    {
        $svg = (new EndroidQrCodeRenderer())->render(self::URL, new RenderOptions('#0b3d91', '#fff4d6', false), ImageFormat::SVG);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($svg));
        self::assertSame('svg', $document->documentElement?->nodeName);
        self::assertStringContainsString('#0b3d91', $svg);
        self::assertStringContainsString('#fff4d6', $svg);
        self::assertStringNotContainsString('<script', $svg);
    }

    public function testTransparentSvgBackgroundHasNoOpacity(): void
    {
        $svg = (new EndroidQrCodeRenderer())->render(self::URL, new RenderOptions(), ImageFormat::SVG);

        self::assertMatchesRegularExpression('/fill-opacity="0"/', $svg);
    }

    public function testLogoIsDrawnInTheCentreAndRaisesTheErrorCorrection(): void
    {
        $this->logoPath = $this->createLogo();
        $renderer = new EndroidQrCodeRenderer();

        $withoutLogo = $this->decode($renderer->render(self::URL, new RenderOptions(size: 800, errorCorrectionLevel: 'L'), ImageFormat::PNG));
        $withLogo = $this->decode($renderer->render(self::URL, new RenderOptions(size: 800, errorCorrectionLevel: 'L', logoPath: $this->logoPath), ImageFormat::PNG));

        $centre = $this->pixel($withLogo, 400, 400);
        self::assertSame([220, 30, 60], [$centre['red'], $centre['green'], $centre['blue']]);

        // Level H needs more modules than level L for the same content: modules get smaller.
        self::assertLessThan($this->moduleSize($withoutLogo), $this->moduleSize($withLogo));
    }

    public function testSvgAcceptsALogo(): void
    {
        $this->logoPath = $this->createLogo();

        $svg = (new EndroidQrCodeRenderer())->render(self::URL, new RenderOptions(logoPath: $this->logoPath), ImageFormat::SVG);

        self::assertStringContainsString('data:image/png;base64,', $svg);
    }

    private function createLogo(): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'qr_test_logo_');
        self::assertIsString($path);

        $logo = \imagecreatetruecolor(120, 120);
        self::assertNotFalse($logo);
        \imagefill($logo, 0, 0, (int) \imagecolorallocate($logo, 220, 30, 60));
        \imagepng($logo, $path);

        return $path;
    }

    private function decode(string $png): \GdImage
    {
        $image = \imagecreatefromstring($png);
        self::assertInstanceOf(\GdImage::class, $image);

        return $image;
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function pixel(\GdImage $image, int $x, int $y): array
    {
        $index = \imagecolorat($image, $x, $y);
        self::assertIsInt($index);

        return \imagecolorsforindex($image, $index);
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function findOpaquePixel(\GdImage $image): array
    {
        for ($offset = 0; $offset < \imagesx($image); ++$offset) {
            $pixel = $this->pixel($image, $offset, $offset);
            if (0 === $pixel['alpha']) {
                return $pixel;
            }
        }

        self::fail('The image has no opaque pixel on its diagonal.');
    }

    /**
     * Width in pixels of the top-left finder pattern's outer ring, i.e. of one module.
     */
    private function moduleSize(\GdImage $image): int
    {
        $start = null;
        for ($offset = 0; $offset < \imagesx($image); ++$offset) {
            $opaque = 0 === $this->pixel($image, $offset, $offset)['alpha'];

            if (null === $start && $opaque) {
                $start = $offset;
            } elseif (null !== $start && !$opaque) {
                return $offset - $start;
            }
        }

        self::fail('Unable to measure the size of a module.');
    }
}
