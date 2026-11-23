<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Generator;

use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepositoryInterface;

/**
 * Generates the short public code written into the QR code URL.
 *
 * The alphabet is lower case only and skips look-alike characters (0/o, 1/i/l):
 * codes survive being typed by hand or upper-cased by a third party, and stay
 * compatible with the QR "alphanumeric" mode should we ever emit upper-case URLs.
 */
final class ShortCodeGenerator
{
    public const LENGTH = 10;
    public const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    private const MAX_ATTEMPTS = 20;

    public function __construct(
        private readonly QrCodeRepositoryInterface $repository,
    ) {
    }

    /**
     * Returns a code that is not used by any QR code yet.
     */
    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $code = self::random();

            if (!$this->repository->codeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException(\sprintf('Unable to generate a unique QR code identifier after %d attempts.', self::MAX_ATTEMPTS));
    }

    public static function random(int $length = self::LENGTH): string
    {
        $max = \strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            $code .= self::ALPHABET[\random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Normalises a code coming from a URL so that lookups are case-insensitive
     * on every database, whatever its collation.
     */
    public static function normalize(string $code): string
    {
        return \strtolower(\trim($code));
    }
}
