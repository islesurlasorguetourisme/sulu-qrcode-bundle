<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures;

use Islesurlasorguetourisme\SuluQrCodeBundle\Storage\QrCodeStorageInterface;

final class InMemoryQrCodeStorage implements QrCodeStorageInterface
{
    /**
     * @var array<string, array<string, string>>
     */
    public array $files = [];

    public function read(string $qrCodeId, string $fileName): ?string
    {
        return $this->files[$qrCodeId][$fileName] ?? null;
    }

    public function write(string $qrCodeId, string $fileName, string $content): void
    {
        $this->files[$qrCodeId][$fileName] = $content;
    }

    public function purge(string $qrCodeId): void
    {
        unset($this->files[$qrCodeId]);
    }
}
