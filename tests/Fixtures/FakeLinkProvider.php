<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures;

use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfigurationBuilder;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;

/**
 * A link provider whose resolvable links are declared per locale.
 */
final class FakeLinkProvider implements LinkProviderInterface
{
    /**
     * Locales the provider was asked for, in order.
     *
     * @var list<string>
     */
    public array $requestedLocales = [];

    /**
     * @param array<string, array<string, string>> $urlsByLocale [locale => [href => url]]
     */
    public function __construct(
        private readonly array $urlsByLocale,
        private readonly string $title = 'Fake page',
    ) {
    }

    public function getConfigurationBuilder(): LinkConfigurationBuilder
    {
        return LinkConfigurationBuilder::create()
            ->setTitle('Fake')
            ->setResourceKey('fake')
            ->setListAdapter('table')
            ->setDisplayProperties(['title'])
            ->setOverlayTitle('Fake')
            ->setEmptyText('Nothing')
            ->setIcon('su-document');
    }

    public function preload(array $hrefs, string $locale, bool $published = true): iterable
    {
        $this->requestedLocales[] = $locale;

        $items = [];
        foreach ($hrefs as $href) {
            if (isset($this->urlsByLocale[$locale][$href])) {
                $items[] = new LinkItem($href, $this->title, $this->urlsByLocale[$locale][$href], $published);
            }
        }

        return $items;
    }
}
