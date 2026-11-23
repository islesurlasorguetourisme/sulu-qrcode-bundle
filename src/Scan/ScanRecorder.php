<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Scan;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCodeDailyScan;

/**
 * Counts a scan: one more for the day, one more for the total.
 *
 * Counters are incremented in SQL ("count = count + 1") rather than through the
 * ORM so that concurrent scans are never lost. Nothing about the visitor is stored.
 */
final class ScanRecorder implements ScanRecorderInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function record(string $qrCodeId, \DateTimeImmutable $scannedAt): void
    {
        if (0 === $this->incrementDay($qrCodeId, $scannedAt)) {
            try {
                $this->connection->insert(
                    QrCodeDailyScan::TABLE_NAME,
                    ['qr_code_id' => $qrCodeId, 'scan_date' => $scannedAt, 'scan_count' => 1],
                    ['qr_code_id' => Types::STRING, 'scan_date' => Types::DATE_IMMUTABLE, 'scan_count' => Types::INTEGER],
                );
            } catch (UniqueConstraintViolationException) {
                // Another scan created the row of the day in the meantime.
                $this->incrementDay($qrCodeId, $scannedAt);
            }
        }

        $this->connection->executeStatement(
            'UPDATE ' . QrCode::TABLE_NAME . ' SET scan_count = scan_count + 1, last_scanned_at = :scannedAt WHERE id = :id',
            ['scannedAt' => $scannedAt, 'id' => $qrCodeId],
            ['scannedAt' => Types::DATETIME_IMMUTABLE, 'id' => Types::STRING],
        );
    }

    private function incrementDay(string $qrCodeId, \DateTimeImmutable $scannedAt): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . QrCodeDailyScan::TABLE_NAME . ' SET scan_count = scan_count + 1 WHERE qr_code_id = :id AND scan_date = :day',
            ['id' => $qrCodeId, 'day' => $scannedAt],
            ['id' => Types::STRING, 'day' => Types::DATE_IMMUTABLE],
        );
    }
}
