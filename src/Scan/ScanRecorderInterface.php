<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Scan;

/**
 * Counts the scans of the QR codes.
 *
 * Alias this interface to your own service to record scans differently
 * (asynchronously through a message bus, in another store…).
 */
interface ScanRecorderInterface
{
    public function record(string $qrCodeId, \DateTimeImmutable $scannedAt): void;
}
