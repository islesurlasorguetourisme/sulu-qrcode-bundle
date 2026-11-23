<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Islesurlasorguetourisme\SuluQrCodeBundle\Admin\QrCodeAdmin;
use Islesurlasorguetourisme\SuluQrCodeBundle\Api\ListFactory;
use Islesurlasorguetourisme\SuluQrCodeBundle\Api\QrCodeNormalizer;
use Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Admin\QrCodeController;
use Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Admin\QrCodeDailyScanController;
use Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Website\QrCodeRedirectController;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\ShortCodeGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\QrCodeImageManager;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Mapper\QrCodeDataMapper;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\EndroidQrCodeRenderer;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\QrCodeRendererInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepository;
use Islesurlasorguetourisme\SuluQrCodeBundle\Repository\QrCodeRepositoryInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\BotDetector;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\ScanRecorder;
use Islesurlasorguetourisme\SuluQrCodeBundle\Scan\ScanRecorderInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Storage\LocalQrCodeStorage;
use Islesurlasorguetourisme\SuluQrCodeBundle\Storage\QrCodeStorageInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Persistence
    $services->set('sulu_qr_code.repository', QrCodeRepository::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(QrCodeRepositoryInterface::class, 'sulu_qr_code.repository');

    $services->set('sulu_qr_code.scan_recorder', ScanRecorder::class)
        ->args([service('doctrine.dbal.default_connection')]);
    $services->alias(ScanRecorderInterface::class, 'sulu_qr_code.scan_recorder');

    // Identifiers and URLs
    $services->set('sulu_qr_code.short_code_generator', ShortCodeGenerator::class)
        ->args([service('sulu_qr_code.repository')]);
    $services->alias(ShortCodeGenerator::class, 'sulu_qr_code.short_code_generator');

    $services->set('sulu_qr_code.public_url_generator', PublicUrlGenerator::class)
        ->args([
            service('router'),
            param('sulu_qr_code.base_url'),
        ]);
    $services->alias(PublicUrlGenerator::class, 'sulu_qr_code.public_url_generator');

    $services->set('sulu_qr_code.target_url_resolver', TargetUrlResolver::class)
        ->args([
            service('sulu_markup.link_tag.provider_pool'),
            service('sulu_core.webspace.webspace_manager'),
        ]);
    $services->alias(TargetUrlResolver::class, 'sulu_qr_code.target_url_resolver');

    // Rendering and storage. Alias the interfaces to your own services to replace them.
    $services->set('sulu_qr_code.renderer', EndroidQrCodeRenderer::class);
    $services->alias(QrCodeRendererInterface::class, 'sulu_qr_code.renderer');

    $services->set('sulu_qr_code.storage', LocalQrCodeStorage::class)
        ->args([param('sulu_qr_code.storage_directory')]);
    $services->alias(QrCodeStorageInterface::class, 'sulu_qr_code.storage');

    $services->set('sulu_qr_code.logo_resolver', LogoResolver::class)
        ->args([service('sulu_media.storage')]);
    $services->alias(LogoResolver::class, 'sulu_qr_code.logo_resolver');

    $services->set('sulu_qr_code.image_manager', QrCodeImageManager::class)
        ->args([
            service('sulu_qr_code.public_url_generator'),
            service(QrCodeRendererInterface::class),
            service(QrCodeStorageInterface::class),
            service('sulu_qr_code.logo_resolver'),
        ]);
    $services->alias(QrCodeImageManager::class, 'sulu_qr_code.image_manager');

    // Administration
    $services->set('sulu_qr_code.admin', QrCodeAdmin::class)
        ->args([
            service('sulu_admin.view_builder_factory'),
            service('sulu_security.security_checker'),
        ])
        ->tag('sulu.admin')
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set('sulu_qr_code.data_mapper', QrCodeDataMapper::class)
        ->args([
            service('sulu_qr_code.short_code_generator'),
            service('sulu_markup.link_tag.provider_pool'),
            service('sulu_tag.tag_manager'),
            service('sulu.repository.media'),
            service('sulu_qr_code.logo_resolver'),
            service('sulu_security.security_checker'),
        ]);

    $services->set('sulu_qr_code.normalizer', QrCodeNormalizer::class)
        ->args([
            service('sulu_qr_code.public_url_generator'),
            service('sulu_qr_code.image_manager'),
            service('sulu_qr_code.target_url_resolver'),
            service('router'),
            service('translator'),
        ]);

    $services->set('sulu_qr_code.list_factory', ListFactory::class)
        ->args([
            service('sulu_core.list_builder.field_descriptor_factory'),
            service('sulu_core.doctrine_list_builder_factory'),
            service('sulu_core.doctrine_rest_helper'),
        ]);

    $services->set('sulu_qr_code.admin.qr_code_controller', QrCodeController::class)
        ->public()
        ->args([
            service('sulu_qr_code.repository'),
            service('sulu_qr_code.data_mapper'),
            service('sulu_qr_code.normalizer'),
            service('sulu_qr_code.image_manager'),
            service('sulu_qr_code.list_factory'),
            service('doctrine.orm.entity_manager'),
            service('translator'),
            service('security.token_storage'),
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set('sulu_qr_code.admin.qr_code_daily_scan_controller', QrCodeDailyScanController::class)
        ->public()
        ->args([service('sulu_qr_code.list_factory')])
        ->tag('sulu.context', ['context' => 'admin']);

    // Public endpoint
    $services->set('sulu_qr_code.bot_detector', BotDetector::class);

    $services->set('sulu_qr_code.website.redirect_controller', QrCodeRedirectController::class)
        ->public()
        ->args([
            service('sulu_qr_code.repository'),
            service('sulu_qr_code.target_url_resolver'),
            service(ScanRecorderInterface::class),
            service('sulu_qr_code.bot_detector'),
            service('event_dispatcher'),
            param('sulu_qr_code.fallback_url'),
            param('sulu_qr_code.ignore_bots'),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'sulu_qr_code']);
};
