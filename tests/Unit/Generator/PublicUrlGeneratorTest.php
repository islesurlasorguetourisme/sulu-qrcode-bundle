<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Generator;

use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PublicUrlGeneratorTest extends TestCase
{
    public function testUsesTheHostOfTheCurrentRequestByDefault(): void
    {
        self::assertSame(
            'https://admin.example.test/qr/k7qm2xw9pb',
            TestFactory::publicUrlGenerator()->generate('k7qm2xw9pb'),
        );
    }

    public function testPinnedBaseUrlWinsOverTheCurrentHost(): void
    {
        self::assertSame(
            'https://www.example.com/qr/k7qm2xw9pb',
            TestFactory::publicUrlGenerator('https://www.example.com')->generate('k7qm2xw9pb'),
        );
    }

    public function testPinnedBaseUrlIgnoresTheFrontControllerOfTheAdministration(): void
    {
        $routes = new RouteCollection();
        $routes->add(PublicUrlGenerator::ROUTE_NAME, new Route('/qr/{code}'));

        $generator = new PublicUrlGenerator(
            new UrlGenerator($routes, new RequestContext('/index.php', 'GET', 'admin.example.test', 'https')),
            'https://www.example.com',
        );

        self::assertSame('https://www.example.com/qr/abc234', $generator->generate('abc234'));
    }

    public function testExplainsHowToFixAMissingRoute(): void
    {
        $generator = new PublicUrlGenerator(new UrlGenerator(new RouteCollection(), new RequestContext()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('routing_website.yaml');

        $generator->generate('abc234');
    }
}
