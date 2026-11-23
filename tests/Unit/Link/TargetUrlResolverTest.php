<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Link;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\FakeLinkProvider;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MarkupBundle\Markup\Link\ExternalLinkProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TargetUrlResolverTest extends TestCase
{
    use SuluStubsTrait;

    private const PAGE = '01a207a4-3d1c-7e01-8179-65c539c4e771';

    public function testFollowsTheLanguageOfTheVisitorWhenTheTranslationExists(): void
    {
        $provider = new FakeLinkProvider([
            'fr' => [self::PAGE => '/fr/marche'],
            'en' => [self::PAGE => '/en/market'],
        ]);

        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: true);

        self::assertSame('/en/market', $this->createResolver($provider)->resolve($qrCode, ['en_US', 'en']));
    }

    public function testFallsBackToTheChosenLocaleWhenTheTranslationIsMissing(): void
    {
        $provider = new FakeLinkProvider(['fr' => [self::PAGE => '/fr/marche']]);
        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: true);

        self::assertSame('/fr/marche', $this->createResolver($provider)->resolve($qrCode, ['en']));
        self::assertSame(['en', 'fr'], $provider->requestedLocales);
    }

    public function testIgnoresTheVisitorLanguageWhenTheOptionIsOff(): void
    {
        $provider = new FakeLinkProvider([
            'fr' => [self::PAGE => '/fr/marche'],
            'en' => [self::PAGE => '/en/market'],
        ]);
        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: false);

        self::assertSame('/fr/marche', $this->createResolver($provider)->resolve($qrCode, ['en']));
        self::assertSame(['fr'], $provider->requestedLocales);
    }

    public function testAnyPublishedTranslationBeatsADeadLink(): void
    {
        // The French page was unpublished after the QR code was printed.
        $provider = new FakeLinkProvider(['en' => [self::PAGE => '/en/market']]);
        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: false);

        self::assertSame('/en/market', $this->createResolver($provider)->resolve($qrCode));
    }

    public function testReturnsNullWhenTheTargetIsGone(): void
    {
        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: true);

        self::assertNull($this->createResolver(new FakeLinkProvider([]))->resolve($qrCode, ['fr']));
    }

    public function testReturnsNullForAProviderThatIsNotInstalled(): void
    {
        $qrCode = new QrCode('abc234', 'Article', 'article', '42');

        self::assertNull($this->createResolver(new FakeLinkProvider([]))->resolve($qrCode));
    }

    public function testAppendsQueryStringAndAnchor(): void
    {
        $provider = new FakeLinkProvider(['fr' => [self::PAGE => '/fr/marche']]);
        $qrCode = $this->createPageQrCode('fr', followVisitorLocale: false);
        $qrCode->setLink('page', self::PAGE, 'fr', ['query' => '?utm_source=qr', 'anchor' => '#horaires']);

        self::assertSame('/fr/marche?utm_source=qr#horaires', $this->createResolver($provider)->resolve($qrCode));
    }

    public function testMergesTheQueryStringWithAnExistingOne(): void
    {
        self::assertSame(
            'https://example.org/?a=1&b=2',
            TargetUrlResolver::appendQueryAndAnchor('https://example.org/?a=1', ['query' => 'b=2']),
        );
    }

    public function testResolvesExternalUrlsWithSulusOwnProvider(): void
    {
        $qrCode = new QrCode('abc234', 'Tickets', 'external', 'https://example.org/tickets');

        $resolver = new TargetUrlResolver(
            TestFactory::linkProviderPool(['external' => new ExternalLinkProvider($this->createStub(TranslatorInterface::class))]),
            $this->createWebspaceManager([]),
        );

        self::assertSame('https://example.org/tickets', $resolver->resolve($qrCode, ['ja']));
    }

    private function createPageQrCode(string $locale, bool $followVisitorLocale): QrCode
    {
        $qrCode = new QrCode('abc234', 'Market', 'page', self::PAGE);
        $qrCode->setLink('page', self::PAGE, $locale);
        $qrCode->setFollowVisitorLocale($followVisitorLocale);

        return $qrCode;
    }

    private function createResolver(FakeLinkProvider $provider): TargetUrlResolver
    {
        return new TargetUrlResolver(
            TestFactory::linkProviderPool(['page' => $provider]),
            $this->createWebspaceManager(['fr', 'en']),
        );
    }
}
