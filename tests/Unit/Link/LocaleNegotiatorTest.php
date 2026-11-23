<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Link;

use Islesurlasorguetourisme\SuluQrCodeBundle\Link\LocaleNegotiator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocaleNegotiatorTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>, list<string>}>
     */
    public static function provideNegotiations(): iterable
    {
        yield 'exact match' => [['en'], ['fr', 'en'], ['en']];
        yield 'regional browser language falls back to the language' => [['en_US', 'fr'], ['fr', 'en'], ['en', 'fr']];
        yield 'dash separated and upper case' => [['de-AT'], ['fr', 'de_at'], ['de_at']];
        yield 'language matches a regional website locale' => [['de'], ['fr', 'de_ch'], ['de_ch']];
        yield 'exact regional match comes before other variants' => [['de_AT'], ['de_ch', 'de_at', 'de'], ['de_at', 'de', 'de_ch']];
        yield 'preference order is kept' => [['it', 'en', 'fr'], ['fr', 'en'], ['en', 'fr']];
        yield 'nothing in common' => [['ja', 'zh_CN'], ['fr', 'en'], []];
        yield 'no header' => [[], ['fr', 'en'], []];
        yield 'duplicates are removed' => [['en_GB', 'en_US', 'en'], ['en'], ['en']];
        yield 'website spelling is preserved' => [['EN'], ['En'], ['En']];
    }

    /**
     * @param list<string> $visitorLanguages
     * @param list<string> $availableLocales
     * @param list<string> $expected
     */
    #[DataProvider('provideNegotiations')]
    public function testNegotiate(array $visitorLanguages, array $availableLocales, array $expected): void
    {
        self::assertSame($expected, LocaleNegotiator::negotiate($visitorLanguages, $availableLocales));
    }
}
