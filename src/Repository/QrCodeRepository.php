<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;

final class QrCodeRepository implements QrCodeRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?QrCode
    {
        return $this->entityManager->find(QrCode::class, $id);
    }

    public function findOneByCode(string $code): ?QrCode
    {
        return $this->entityManager->getRepository(QrCode::class)->findOneBy(['code' => $code]);
    }

    public function codeExists(string $code): bool
    {
        return 0 < $this->entityManager->getRepository(QrCode::class)->count(['code' => $code]);
    }

    public function add(QrCode $qrCode): void
    {
        $this->entityManager->persist($qrCode);
    }

    public function remove(QrCode $qrCode): void
    {
        $this->entityManager->remove($qrCode);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
