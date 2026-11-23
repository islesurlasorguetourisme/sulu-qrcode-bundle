<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures;

use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPool;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Builds the collaborators shared by several test cases.
 */
final class TestFactory
{
    private function __construct()
    {
    }

    /**
     * @param array<string, LinkProviderInterface> $providers
     */
    public static function linkProviderPool(array $providers): LinkProviderPool
    {
        return new LinkProviderPool($providers);
    }

    public static function publicUrlGenerator(?string $baseUrl = null, string $host = 'admin.example.test'): PublicUrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(PublicUrlGenerator::ROUTE_NAME, new Route('/qr/{code}'));

        return new PublicUrlGenerator(
            new UrlGenerator($routes, new RequestContext('', 'GET', $host, 'https')),
            $baseUrl,
        );
    }
}
