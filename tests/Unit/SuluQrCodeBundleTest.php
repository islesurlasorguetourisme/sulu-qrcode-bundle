<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit;

use Islesurlasorguetourisme\SuluQrCodeBundle\SuluQrCodeBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

final class SuluQrCodeBundleTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        self::assertSame(
            [
                'base_url' => null,
                'storage_directory' => '%kernel.project_dir%/var/storage/qr_codes',
                'fallback_url' => null,
                'ignore_bots' => true,
            ],
            $this->process([]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideValidConfigurations(): iterable
    {
        yield 'https base url' => [['base_url' => 'https://www.example.com']];
        yield 'fallback to the home page' => [['fallback_url' => '/']];
        yield 'fallback to a path' => [['fallback_url' => '/fr/qr-code-indisponible']];
        yield 'fallback to another site' => [['fallback_url' => 'https://www.example.com/sorry?from=qr']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideValidConfigurations')]
    public function testAcceptsValidConfiguration(array $config): void
    {
        $processed = $this->process($config);

        foreach ($config as $key => $value) {
            self::assertSame($value, $processed[$key]);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'base url without scheme' => [['base_url' => 'www.example.com']];
        yield 'base url with another scheme' => [['base_url' => 'ftp://example.com']];
        yield 'javascript fallback' => [['fallback_url' => 'javascript:alert(1)']];
        yield 'protocol-relative fallback' => [['fallback_url' => '//evil.example']];
        yield 'backslash fallback' => [['fallback_url' => '/\\evil.example']];
        yield 'relative fallback' => [['fallback_url' => 'sorry']];
        yield 'fallback with a line break' => [['fallback_url' => "https://example.com/\r\nX-Injected: 1"]];
        yield 'empty storage directory' => [['storage_directory' => '']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideInvalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new SuluQrCodeBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration($configuration, [$config]);

        return $processed;
    }
}
