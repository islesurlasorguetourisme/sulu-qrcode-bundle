<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Scan;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells automated traffic (crawlers, link previewers, monitoring, scripts) from real scans.
 *
 * It is a deliberately simple heuristic on the User-Agent header: good enough to
 * keep statistics honest, not a security measure. In-app browsers of social
 * networks are real people and are not excluded; only their preview fetchers are.
 */
final class BotDetector
{
    private const PATTERN = '/(?<!cu)bot\b|crawl|spider|slurp|preview|facebookexternalhit|^whatsapp\/|embedly|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|headless|lighthouse|pingdom|uptime|monitor|statuscake|datadog/i';

    public function isBot(Request $request): bool
    {
        $userAgent = \trim((string) $request->headers->get('User-Agent', ''));

        return '' === $userAgent || 1 === \preg_match(self::PATTERN, $userAgent);
    }
}
