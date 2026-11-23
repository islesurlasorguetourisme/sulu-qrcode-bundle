<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Generator;

use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\ShortCodeGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\InMemoryQrCodeRepository;
use PHPUnit\Framework\TestCase;

final class ShortCodeGeneratorTest extends TestCase
{
    public function testRandomCodeUsesTheUnambiguousLowerCaseAlphabet(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $code = ShortCodeGenerator::random();

            self::assertSame(ShortCodeGenerator::LENGTH, \strlen($code));
            self::assertMatchesRegularExpression('/^[2-9a-hjkmnp-z]+$/', $code);
        }
    }

    public function testAlphabetHasNoLookAlikeCharacters(): void
    {
        foreach (['0', 'o', '1', 'i', 'l'] as $ambiguous) {
            self::assertStringNotContainsString($ambiguous, ShortCodeGenerator::ALPHABET);
        }

        self::assertSame(\strtolower(ShortCodeGenerator::ALPHABET), ShortCodeGenerator::ALPHABET);
    }

    public function testGenerateRetriesUntilTheCodeIsFree(): void
    {
        $repository = new InMemoryQrCodeRepository();
        $repository->reservedCodes = ['taken', 'taken', 'taken'];

        $code = (new ShortCodeGenerator($repository))->generate();

        self::assertSame(ShortCodeGenerator::LENGTH, \strlen($code));
        self::assertSame(4, $repository->codeExistsCalls);
    }

    public function testGenerateGivesUpInsteadOfLoopingForever(): void
    {
        $repository = new InMemoryQrCodeRepository();
        $repository->reservedCodes = \array_fill(0, 100, 'taken');

        $this->expectException(\RuntimeException::class);

        (new ShortCodeGenerator($repository))->generate();
    }

    public function testNormalizeMakesLookupsCaseInsensitive(): void
    {
        self::assertSame('k7qm2xw9pb', ShortCodeGenerator::normalize(' K7QM2xW9pB '));
    }
}
