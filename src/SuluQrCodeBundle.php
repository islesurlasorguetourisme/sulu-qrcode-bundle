<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle;

use Islesurlasorguetourisme\SuluQrCodeBundle\Admin\QrCodeAdmin;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCodeDailyScan;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Dynamic QR codes for Sulu: a printed code points to a stable short URL
 * whose target can be changed at any time from the administration.
 */
final class SuluQrCodeBundle extends AbstractBundle
{
    protected string $extensionAlias = 'sulu_qr_code';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode
            ->children()
                ->scalarNode('base_url')
                    ->defaultNull()
                    ->info('Scheme and host written into the QR codes, e.g. "https://www.example.com". When null, the host of the current request is used. Set it explicitly in production: it ends up printed on paper.')
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || 1 !== \preg_match('#^https?://[^/?\#]+#i', $value)))
                        ->thenInvalid('The "base_url" option must be an absolute http(s) URL, got %s.')
                    ->end()
                ->end()
                ->scalarNode('storage_directory')
                    ->defaultValue('%kernel.project_dir%/var/storage/qr_codes')
                    ->cannotBeEmpty()
                    ->info('Local directory holding the generated image files. It is a regenerable cache: the database stays the source of truth.')
                ->end()
                ->scalarNode('fallback_url')
                    ->defaultNull()
                    ->info('Where a scan is redirected when the target cannot be resolved (unpublished or deleted page): an absolute http(s) URL or a path starting with "/". When null, the endpoint answers 404.')
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || 1 !== \preg_match('#^(https?://[^\s/?\#]+\S*|/(?![/\\\\])\S*)$#i', $value)))
                        ->thenInvalid('The "fallback_url" option must be an absolute http(s) URL or a path starting with "/", got %s.')
                    ->end()
                ->end()
                ->booleanNode('ignore_bots')
                    ->defaultTrue()
                    ->info('Do not count requests made by crawlers and link previewers as scans.')
                ->end()
            ->end();
    }

    /**
     * @param array{base_url: string|null, storage_directory: string, fallback_url: string|null, ignore_bots: bool} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('sulu_qr_code.base_url', null !== $config['base_url'] ? \rtrim($config['base_url'], '/') : null)
            ->set('sulu_qr_code.storage_directory', $config['storage_directory'])
            ->set('sulu_qr_code.fallback_url', $config['fallback_url'])
            ->set('sulu_qr_code.ignore_bots', $config['ignore_bots']);

        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('sulu_admin')) {
            $builder->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [$this->getPath() . '/config/lists'],
                ],
                'forms' => [
                    'directories' => [$this->getPath() . '/config/forms'],
                ],
                'resources' => [
                    QrCode::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'sulu_qr_code.get_qr_codes',
                            'detail' => 'sulu_qr_code.get_qr_code',
                        ],
                    ],
                    QrCodeDailyScan::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'sulu_qr_code.get_qr_code_daily_scans',
                        ],
                    ],
                    QrCodeAdmin::FILES_RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'sulu_qr_code.get_qr_code_files',
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('doctrine')) {
            // Named after the bundle on purpose: it merges with Doctrine's "auto_mapping"
            // when enabled, and makes the entities available when it is not.
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'SuluQrCodeBundle' => [
                            'type' => 'attribute',
                            'dir' => $this->getPath() . '/src/Entity',
                            'prefix' => 'Islesurlasorguetourisme\SuluQrCodeBundle\Entity',
                            'alias' => 'SuluQrCodeBundle',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }
    }
}
