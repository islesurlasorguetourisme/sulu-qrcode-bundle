<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Scan;

use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\BotDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BotDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideUserAgents(): iterable
    {
        // People
        yield 'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', false];
        yield 'Android Chrome' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', false];
        yield 'Cubot phone (name ends with "bot")' => ['Mozilla/5.0 (Linux; Android 11; CUBOT NOTE 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', false];
        yield 'Instagram in-app browser' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 330.0.0.0', false];
        yield 'LinkedIn in-app browser' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [LinkedInApp]/9.29', false];

        // Machines
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true];
        yield 'Bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', true];
        yield 'Facebook preview' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', true];
        yield 'WhatsApp preview' => ['WhatsApp/2.23.20.0 A', true];
        yield 'Slack preview' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', true];
        yield 'Skype preview' => ['Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5', true];
        yield 'curl' => ['curl/8.7.1', true];
        yield 'python' => ['python-requests/2.32.3', true];
        yield 'okhttp prefetch' => ['okhttp/4.12.0', true];
        yield 'headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36', true];
        yield 'uptime monitor' => ['Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)', true];
        yield 'empty' => ['', true];
    }

    #[DataProvider('provideUserAgents')]
    public function testIsBot(string $userAgent, bool $expected): void
    {
        $request = Request::create('/qr/abc234');
        $request->headers->set('User-Agent', $userAgent);

        self::assertSame($expected, (new BotDetector())->isBot($request));
    }

    public function testMissingUserAgentIsABot(): void
    {
        $request = Request::create('/qr/abc234');
        $request->headers->remove('User-Agent');

        self::assertTrue((new BotDetector())->isBot($request));
    }
}
