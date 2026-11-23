<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Repository;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;

interface QrCodeRepositoryInterface
{
    public function findById(string $id): ?QrCode;

    /**
     * @param string $code The public short code, already normalised (lower case)
     */
    public function findOneByCode(string $code): ?QrCode;

    public function codeExists(string $code): bool;

    public function add(QrCode $qrCode): void;

    public function remove(QrCode $qrCode): void;

    public function flush(): void;
}
