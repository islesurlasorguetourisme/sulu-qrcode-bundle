<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Api;

/**
 * Makes the rows returned by Sulu's list builder safe to encode as JSON.
 */
final class ListRowNormalizer
{
    private function __construct()
    {
    }

    /**
     * Dates come out of the list builder as objects; the administration expects ISO 8601 strings.
     *
     * @param iterable<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(iterable $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if ($value instanceof \DateTimeInterface) {
                    $row[$key] = $value->format(\DATE_ATOM);
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
