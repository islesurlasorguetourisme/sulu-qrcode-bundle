<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepositoryInterface;

final class InMemoryQrCodeRepository implements QrCodeRepositoryInterface
{
    /**
     * @var array<string, QrCode>
     */
    private array $qrCodes = [];

    /**
     * Codes reported as taken although no QR code uses them, to simulate collisions.
     *
     * @var list<string>
     */
    public array $reservedCodes = [];

    public int $codeExistsCalls = 0;

    public function findById(string $id): ?QrCode
    {
        return $this->qrCodes[$id] ?? null;
    }

    public function findOneByCode(string $code): ?QrCode
    {
        foreach ($this->qrCodes as $qrCode) {
            if ($qrCode->getCode() === $code) {
                return $qrCode;
            }
        }

        return null;
    }

    public function codeExists(string $code): bool
    {
        ++$this->codeExistsCalls;

        if ([] !== $this->reservedCodes) {
            \array_shift($this->reservedCodes);

            return true;
        }

        return null !== $this->findOneByCode($code);
    }

    public function add(QrCode $qrCode): void
    {
        $this->qrCodes[$qrCode->getId()] = $qrCode;
    }

    public function remove(QrCode $qrCode): void
    {
        unset($this->qrCodes[$qrCode->getId()]);
    }

    public function flush(): void
    {
    }
}
