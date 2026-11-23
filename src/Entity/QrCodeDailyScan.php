<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Number of scans of one QR code on one calendar day.
 *
 * Rows are written with raw SQL by the scan recorder (atomic increments); this
 * entity exists for the schema and for read access. It holds no personal data.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_qr_code_daily_scans_day', columns: ['qr_code_id', 'scan_date'])]
class QrCodeDailyScan
{
    public const RESOURCE_KEY = 'qr_code_daily_scans';
    public const TABLE_NAME = 'qr_code_daily_scans';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QrCode::class)]
    #[ORM\JoinColumn(name: 'qr_code_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QrCode $qrCode;

    #[ORM\Column(name: 'scan_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $scanDate;

    #[ORM\Column(name: 'scan_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $scanCount = 0;

    public function __construct(QrCode $qrCode, \DateTimeImmutable $scanDate, int $scanCount = 0)
    {
        $this->qrCode = $qrCode;
        $this->scanDate = $scanDate;
        $this->scanCount = $scanCount;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQrCode(): QrCode
    {
        return $this->qrCode;
    }

    public function getScanDate(): \DateTimeImmutable
    {
        return $this->scanDate;
    }

    public function getScanCount(): int
    {
        return $this->scanCount;
    }
}
