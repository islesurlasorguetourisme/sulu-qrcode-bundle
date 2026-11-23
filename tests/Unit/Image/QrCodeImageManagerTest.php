<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Image;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\UnsupportedImageSizeException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\QrCodeImageManager;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\QrCodeRendererInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\RenderOptions;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\InMemoryQrCodeStorage;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;

final class QrCodeImageManagerTest extends TestCase
{
    use SuluStubsTrait;

    /**
     * @var list<array{content: string, options: RenderOptions, format: ImageFormat}>
     */
    private array $renderCalls = [];

    private InMemoryQrCodeStorage $storage;

    protected function setUp(): void
    {
        $this->renderCalls = [];
        $this->storage = new InMemoryQrCodeStorage();
    }

    public function testRendersOnceThenServesTheStoredFile(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        $first = $manager->getImage($qrCode, ImageFormat::PNG);
        $second = $manager->getImage($qrCode, ImageFormat::PNG);

        self::assertCount(1, $this->renderCalls);
        self::assertSame($first->content, $second->content);
        self::assertSame($first->fingerprint, $second->fingerprint);
        self::assertSame('https://www.example.com/qr/abc234', $this->renderCalls[0]['content']);
        self::assertSame(1, $this->countImages($qrCode));
    }

    public function testEachFormatAndSizeIsStoredSeparately(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        $manager->getImage($qrCode, ImageFormat::PNG);
        $manager->getImage($qrCode, ImageFormat::SVG);
        $manager->getImage($qrCode, ImageFormat::PNG, 80);

        self::assertCount(3, $this->renderCalls);
        self::assertSame(3, $this->countImages($qrCode));
        self::assertSame(80, $this->renderCalls[2]['options']->size);
        self::assertSame(3, $this->renderCalls[2]['options']->margin, 'The margin of a thumbnail is scaled down.');
    }

    public function testAChangeOfAppearanceYieldsANewImage(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        $before = $manager->getImage($qrCode, ImageFormat::PNG)->fingerprint;
        $qrCode->setForegroundColor('#0b3d91');
        $after = $manager->getImage($qrCode, ImageFormat::PNG)->fingerprint;

        self::assertNotSame($before, $after);
        self::assertCount(2, $this->renderCalls, 'The stale file must not be served.');
        self::assertSame('#0b3d91', $this->renderCalls[1]['options']->foregroundColor);
    }

    public function testChangingTheTargetDoesNotChangeTheImage(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        $before = $manager->getFingerprint($qrCode);
        $qrCode->setTitle('Renamed');
        $qrCode->setLink('external', 'https://example.org/somewhere-else');

        self::assertSame($before, $manager->getFingerprint($qrCode), 'That is the whole point of a dynamic QR code.');
    }

    public function testAnotherBaseUrlYieldsAnotherImage(): void
    {
        $qrCode = $this->createQrCode();

        self::assertNotSame(
            $this->createManager('https://www.example.com')->getFingerprint($qrCode),
            $this->createManager('https://www.example.org')->getFingerprint($qrCode),
        );
    }

    public function testReplacingTheLogoFileInvalidatesTheImage(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        $qrCode->setLogo($this->createMedia(12, 'image/png', 1));
        $versionOne = $manager->getFingerprint($qrCode);

        $qrCode->setLogo($this->createMedia(12, 'image/png', 2));

        self::assertNotSame($versionOne, $manager->getFingerprint($qrCode));
    }

    public function testOnlyServesAFixedListOfSizes(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        foreach (QrCodeImageManager::PREVIEW_SIZES as $size) {
            $manager->getImage($qrCode, ImageFormat::PNG, $size);
        }
        $manager->getImage($qrCode, ImageFormat::PNG, $qrCode->getSize());

        // The size comes from the URL: each accepted value is a file written to disk.
        foreach ([1, 79, 81, 999, 4000, 999999, -80] as $size) {
            try {
                $manager->getImage($qrCode, ImageFormat::PNG, $size);
                self::fail(\sprintf('Size %d should have been refused.', $size));
            } catch (UnsupportedImageSizeException) {
            }
        }

        self::assertSame(\count(QrCodeImageManager::PREVIEW_SIZES) + 1, $this->countImages($qrCode));
    }

    public function testStorageCannotGrowWithEveryChange(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();

        foreach (['#111111', '#222222', '#333333', '#444444', '#555555'] as $color) {
            $qrCode->setForegroundColor($color);
            $manager->getImage($qrCode, ImageFormat::PNG);
            $manager->getImage($qrCode, ImageFormat::SVG);
            $manager->getImage($qrCode, ImageFormat::PNG, QrCodeImageManager::THUMBNAIL_SIZE);
        }

        self::assertSame(3, $this->countImages($qrCode), 'Only the images of the current appearance are kept.');
    }

    public function testStorageCannotGrowWithEveryHostName(): void
    {
        // Without a pinned base URL, the public URL (hence the fingerprint) follows the Host header.
        $qrCode = $this->createQrCode();

        foreach (['a.test', 'b.test', 'c.test', 'd.test'] as $host) {
            $this->createManager('https://' . $host)->getImage($qrCode, ImageFormat::PNG);
        }

        self::assertSame(1, $this->countImages($qrCode));
    }

    public function testAnImageIsNeverServedForAnotherFingerprint(): void
    {
        $qrCode = $this->createQrCode();

        $first = $this->createManager('https://www.example.com')->getImage($qrCode, ImageFormat::PNG);
        $second = $this->createManager('https://evil.example')->getImage($qrCode, ImageFormat::PNG);

        self::assertNotSame($first->content, $second->content);
        self::assertSame('https://evil.example/qr/abc234', $this->renderCalls[1]['content']);
        self::assertCount(2, $this->renderCalls, 'The image rendered for another host must not be reused.');
    }

    public function testPurgeDropsTheStoredFiles(): void
    {
        $manager = $this->createManager();
        $qrCode = $this->createQrCode();
        $manager->getImage($qrCode, ImageFormat::PNG);

        $manager->purge($qrCode);

        self::assertArrayNotHasKey($qrCode->getId(), $this->storage->files);
    }

    /**
     * Number of stored image files, the fingerprint marker left aside.
     */
    private function countImages(QrCode $qrCode): int
    {
        return \count(\array_filter(
            \array_keys($this->storage->files[$qrCode->getId()] ?? []),
            static fn (string $fileName): bool => \str_contains($fileName, '.'),
        ));
    }

    private function createQrCode(): QrCode
    {
        return new QrCode('abc234', 'Market', 'external', 'https://example.org/market');
    }

    private function createManager(string $baseUrl = 'https://www.example.com'): QrCodeImageManager
    {
        $renderer = $this->createStub(QrCodeRendererInterface::class);
        $renderer->method('render')->willReturnCallback(function (string $content, RenderOptions $options, ImageFormat $format): string {
            $this->renderCalls[] = ['content' => $content, 'options' => $options, 'format' => $format];

            return 'image #' . \count($this->renderCalls);
        });

        return new QrCodeImageManager(
            TestFactory::publicUrlGenerator($baseUrl),
            $renderer,
            $this->storage,
            new LogoResolver($this->createStub(StorageInterface::class)),
        );
    }
}
