<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Storage;

use Islesurlasorguetourisme\SuluQrCodeBundle\Storage\LocalQrCodeStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LocalQrCodeStorageTest extends TestCase
{
    private const ID = '01a207c2-0739-7247-a1da-7adbfa1866a4';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/sulu_qr_code_storage_' . \bin2hex(\random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testReadsBackWhatWasWritten(): void
    {
        $storage = new LocalQrCodeStorage($this->directory);

        self::assertNull($storage->read(self::ID, 'abc-1000.png'));

        $storage->write(self::ID, 'abc-1000.png', 'binary');

        self::assertSame('binary', $storage->read(self::ID, 'abc-1000.png'));
        self::assertFileExists($this->directory . '/' . self::ID . '/abc-1000.png');
    }

    public function testCreatesTheDirectoryOnDemand(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        (new LocalQrCodeStorage($this->directory . '/'))->write(self::ID, 'a.svg', '<svg/>');

        self::assertDirectoryExists($this->directory . '/' . self::ID);
    }

    public function testPurgeOnlyRemovesTheFilesOfOneQrCode(): void
    {
        $storage = new LocalQrCodeStorage($this->directory);
        $storage->write(self::ID, 'a.png', 'a');
        $storage->write(self::ID, 'a.svg', 'a');
        $storage->write('other', 'b.png', 'b');

        $storage->purge(self::ID);

        self::assertNull($storage->read(self::ID, 'a.png'));
        self::assertNull($storage->read(self::ID, 'a.svg'));
        self::assertSame('b', $storage->read('other', 'b.png'));
    }

    public function testPurgingAnUnknownQrCodeIsHarmless(): void
    {
        (new LocalQrCodeStorage($this->directory))->purge(self::ID);

        $this->expectNotToPerformAssertions();
    }

    public function testCannotEscapeTheStorageDirectory(): void
    {
        $storage = new LocalQrCodeStorage($this->directory);
        $storage->write('../../outside', '../evil.png', 'x');

        // Path separators are stripped: the file stays inside the storage directory.
        self::assertFileExists($this->directory . '/....outside/..evil.png');
        self::assertFileDoesNotExist(\dirname($this->directory) . '/evil.png');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidSegments(): iterable
    {
        yield 'empty id' => ['', 'a.png'];
        yield 'dots only id' => ['..', 'a.png'];
        yield 'separators only' => ['///', 'a.png'];
        yield 'empty file name' => [self::ID, ''];
        yield 'parent directory as file name' => [self::ID, '..'];
    }

    #[DataProvider('provideInvalidSegments')]
    public function testRejectsSegmentsThatDoNotNameAFile(string $qrCodeId, string $fileName): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LocalQrCodeStorage($this->directory))->write($qrCodeId, $fileName, 'x');
    }
}
