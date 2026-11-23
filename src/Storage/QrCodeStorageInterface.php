<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Storage;

/**
 * Stores the generated image files of the QR codes.
 *
 * Implementations are a cache: every file can be rendered again from the
 * database, so losing the storage is never a data loss.
 */
interface QrCodeStorageInterface
{
    /**
     * Returns the content of the file, or null when it has not been generated yet.
     */
    public function read(string $qrCodeId, string $fileName): ?string;

    public function write(string $qrCodeId, string $fileName, string $content): void;

    /**
     * Removes every file generated for the given QR code.
     */
    public function purge(string $qrCodeId): void;
}
