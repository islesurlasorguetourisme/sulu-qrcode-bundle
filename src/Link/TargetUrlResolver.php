<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Link;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * Turns the link stored on a QR code into the URL a scan is redirected to.
 *
 * Links are resolved with Sulu's link providers at scan time, never stored as
 * URLs: a page that is renamed or moved keeps working.
 */
final class TargetUrlResolver
{
    public function __construct(
        private readonly LinkProviderPoolInterface $linkProviderPool,
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {
    }

    /**
     * @param iterable<string> $visitorLanguages Languages accepted by the visitor, best first
     * @param bool $published False to also resolve unpublished targets (administration)
     *
     * @return string|null Null when the target does not exist (anymore) or is not published
     */
    public function resolve(QrCode $qrCode, iterable $visitorLanguages = [], bool $published = true): ?string
    {
        $item = $this->resolveItem($qrCode, $visitorLanguages, $published);

        if (null === $item) {
            return null;
        }

        return self::appendQueryAndAnchor($item->getUrl(), $qrCode->getLinkData());
    }

    /**
     * @param iterable<string> $visitorLanguages
     */
    public function resolveItem(QrCode $qrCode, iterable $visitorLanguages = [], bool $published = true): ?LinkItem
    {
        if (!$this->linkProviderPool->hasProvider($qrCode->getLinkProvider())) {
            return null;
        }

        $provider = $this->linkProviderPool->getProvider($qrCode->getLinkProvider());

        foreach ($this->getCandidateLocales($qrCode, $visitorLanguages) as $locale) {
            foreach ($provider->preload([$qrCode->getLinkHref()], $locale, $published) as $item) {
                if ('' !== $item->getUrl()) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param iterable<string> $visitorLanguages
     *
     * @return list<string>
     */
    private function getCandidateLocales(QrCode $qrCode, iterable $visitorLanguages): array
    {
        $availableLocales = $this->getAvailableLocales();
        $locales = [];

        if ($qrCode->isFollowVisitorLocale()) {
            $locales = LocaleNegotiator::negotiate($visitorLanguages, $availableLocales);
        }

        // Fallbacks: the locale chosen in the administration, then any locale of the website.
        if (null !== $qrCode->getLinkLocale()) {
            $locales[] = $qrCode->getLinkLocale();
        }

        $locales = \array_values(\array_unique([...$locales, ...$availableLocales]));

        // Providers that do not depend on a locale (external URLs) still need one to be called.
        return [] !== $locales ? $locales : ['en'];
    }

    /**
     * @return list<string>
     */
    private function getAvailableLocales(): array
    {
        $locales = [];
        foreach ($this->webspaceManager->getAllLocalesByWebspaces() as $webspaceLocales) {
            foreach (\array_keys($webspaceLocales) as $locale) {
                $locales[] = (string) $locale;
            }
        }

        return \array_values(\array_unique($locales));
    }

    /**
     * Adds the query string and the anchor configured on the link to a resolved URL.
     *
     * @param array<string, mixed> $linkData
     */
    public static function appendQueryAndAnchor(string $url, array $linkData): string
    {
        $query = $linkData['query'] ?? null;
        if (\is_string($query) && '' !== \ltrim($query, '?')) {
            $url .= (\str_contains($url, '?') ? '&' : '?') . \ltrim($query, '?');
        }

        $anchor = $linkData['anchor'] ?? null;
        if (\is_string($anchor) && '' !== \ltrim($anchor, '#')) {
            $url .= '#' . \ltrim($anchor, '#');
        }

        return $url;
    }
}
