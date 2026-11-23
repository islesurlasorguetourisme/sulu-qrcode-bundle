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
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;

/**
 * The logo is a file uploaded by a user: these tests treat it as hostile.
 */
#[RequiresPhpExtension('gd')]
final class LogoResolverTest extends TestCase
{
    use SuluStubsTrait;

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @\unlink($file);
        }
    }

    public function testHandsOverAReEncodedPngNeverTheOriginalBytes(): void
    {
        $jpeg = $this->createImage(200, 100, 'jpeg');

        $path = $this->resolve($jpeg, 'image/jpeg');

        self::assertNotNull($path);
        $copy = (string) \file_get_contents($path);
        $info = \getimagesizefromstring($copy);
        self::assertIsArray($info);
        self::assertSame('image/png', $info['mime']);
        self::assertSame([200, 100], [$info[0], $info[1]]);
        self::assertNotSame($jpeg, $copy);
    }

    public function testDropsEverythingThatIsNotPixels(): void
    {
        // A valid image followed by a payload, as in a polyglot file or leftover metadata.
        $png = $this->createImage(50, 50, 'png') . 'SECRET-PAYLOAD<script>alert(1)</script>';

        $path = $this->resolve($png, 'image/png');

        self::assertNotNull($path);
        $copy = (string) \file_get_contents($path);
        self::assertStringNotContainsString('SECRET-PAYLOAD', $copy);
        self::assertStringNotContainsString('<script', $copy);
    }

    public function testDownscalesLargeLogos(): void
    {
        $path = $this->resolve($this->createImage(3000, 1500, 'png'), 'image/png');

        self::assertNotNull($path);
        $info = \getimagesize($path);
        self::assertIsArray($info);
        self::assertSame([1200, 600], [$info[0], $info[1]]);
    }

    public function testKeepsTransparency(): void
    {
        $image = \imagecreatetruecolor(40, 40);
        self::assertNotFalse($image);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);
        \imagefill($image, 0, 0, (int) \imagecolorallocatealpha($image, 0, 0, 0, 127));
        \ob_start();
        \imagepng($image);
        $png = (string) \ob_get_clean();

        $path = $this->resolve($png, 'image/png');

        self::assertNotNull($path);
        $copy = \imagecreatefrompng($path);
        self::assertNotFalse($copy);
        $pixel = \imagecolorat($copy, 0, 0);
        self::assertIsInt($pixel);
        self::assertSame(127, \imagecolorsforindex($copy, $pixel)['alpha']);
    }

    public function testRefusesADecompressionBombWithoutDecodingIt(): void
    {
        // A few bytes declaring a 60000 x 60000 image: decoding it would need gigabytes of memory.
        $bomb = "\x89PNG\r\n\x1a\n" . \pack('N', 13) . 'IHDR' . \pack('NN', 60000, 60000) . "\x08\x06\x00\x00\x00\x00\x00\x00\x00";
        $info = \getimagesizefromstring($bomb);
        self::assertIsArray($info, 'The fixture must look like a PNG to be a relevant test.');
        self::assertSame(60000, $info[0]);

        $memoryBefore = \memory_get_peak_usage();

        self::assertNull($this->resolve($bomb, 'image/png'));
        self::assertLessThan(20_000_000, \memory_get_peak_usage() - $memoryBefore);
    }

    public function testRefusesImagesWithTooManyPixels(): void
    {
        // Each side is acceptable, their product is not.
        $wide = "\x89PNG\r\n\x1a\n" . \pack('N', 13) . 'IHDR' . \pack('NN', 4000, 4000) . "\x08\x06\x00\x00\x00\x00\x00\x00\x00";

        self::assertNull($this->resolve($wide, 'image/png'));
    }

    public function testRefusesWhatIsNotAnImage(): void
    {
        self::assertNull($this->resolve('<?php echo "not an image";', 'image/png'));
        self::assertNull($this->resolve('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/png'));
        self::assertNull($this->resolve('', 'image/png'));
    }

    public function testRefusesAFileBiggerThanDeclared(): void
    {
        // The media library says "small", the storage returns something huge.
        $content = $this->createImage(10, 10, 'png') . \str_repeat('A', LogoResolver::MAX_FILE_SIZE + 10);

        self::assertNull($this->resolve($content, 'image/png', declaredSize: 100));
    }

    public function testAnUnreadableStorageMeansNoLogoNotAnError(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $storage->method('load')->willThrowException(new \RuntimeException('File not found.'));

        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');
        $qrCode->setLogo($this->createMedia(12, 'image/png'));

        self::assertNull((new LogoResolver($storage))->createLocalCopy($qrCode));
    }

    public function testSupportsRasterImagesOfReasonableSizeOnly(): void
    {
        $resolver = new LogoResolver($this->createStub(StorageInterface::class));

        self::assertTrue($resolver->isSupported($this->createMedia(1, 'image/png')));
        self::assertTrue($resolver->isSupported($this->createMedia(1, 'image/webp')));
        self::assertFalse($resolver->isSupported($this->createMedia(1, 'image/svg+xml')), 'An SVG can carry scripts.');
        self::assertFalse($resolver->isSupported($this->createMedia(1, 'application/pdf')));
        self::assertFalse($resolver->isSupported($this->createMedia(1, 'image/png', fileSize: LogoResolver::MAX_FILE_SIZE + 1)));
    }

    public function testVersionKeyFollowsTheFileOfTheMedia(): void
    {
        $resolver = new LogoResolver($this->createStub(StorageInterface::class));
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');

        self::assertNull($resolver->getVersionKey($qrCode));

        $qrCode->setLogo($this->createMedia(12, 'image/png', version: 3));
        self::assertSame('12-3', $resolver->getVersionKey($qrCode));

        $qrCode->setLogo($this->createMedia(12, 'image/svg+xml'));
        self::assertNull($resolver->getVersionKey($qrCode), 'An unusable logo is no logo.');
    }

    private function resolve(string $content, string $mimeType, ?int $declaredSize = null): ?string
    {
        $stream = \fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        \fwrite($stream, $content);
        \rewind($stream);

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('load')->willReturn($stream);

        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');
        $qrCode->setLogo($this->createMedia(12, $mimeType, fileSize: $declaredSize ?? \max(1, \strlen($content))));

        $path = (new LogoResolver($storage))->createLocalCopy($qrCode);

        if (null !== $path) {
            $this->temporaryFiles[] = $path;
        }

        return $path;
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createImage(int $width, int $height, string $format): string
    {
        $image = \imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 220, 30, 60));

        \ob_start();
        'jpeg' === $format ? \imagejpeg($image) : \imagepng($image);

        return (string) \ob_get_clean();
    }
}
