<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Link;

/**
 * Matches the languages accepted by a visitor's browser against the locales of the website.
 */
final class LocaleNegotiator
{
    private function __construct()
    {
    }

    /**
     * @param iterable<string> $visitorLanguages Preferred languages, best first (e.g. "en_US", "en", "fr")
     * @param iterable<string> $availableLocales Locales of the website (e.g. "fr", "en", "de_at")
     *
     * @return list<string> The available locales the visitor can read, best first, as spelled by the website
     */
    public static function negotiate(iterable $visitorLanguages, iterable $availableLocales): array
    {
        $available = [];
        foreach ($availableLocales as $locale) {
            $available[self::normalize($locale)] = $locale;
        }

        $matches = [];
        foreach ($visitorLanguages as $language) {
            $normalized = self::normalize($language);
            if ('' === $normalized) {
                continue;
            }

            $primary = \explode('_', $normalized, 2)[0];

            // 1. exact match ("de_at" for "de-AT"), 2. language only ("de"), 3. any regional variant ("de_ch").
            $candidates = [$normalized, $primary];
            foreach (\array_keys($available) as $key) {
                if (\str_starts_with($key, $primary . '_')) {
                    $candidates[] = $key;
                }
            }

            foreach ($candidates as $candidate) {
                if (isset($available[$candidate]) && !\in_array($available[$candidate], $matches, true)) {
                    $matches[] = $available[$candidate];
                }
            }
        }

        return $matches;
    }

    private static function normalize(string $locale): string
    {
        return \strtolower(\str_replace('-', '_', \trim($locale)));
    }
}
