<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Api;

use Islesurlasorguetourisme\SuluQrCodeBundle\Api\QrCodeNormalizer;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\QrCodeImageManager;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\QrCodeRendererInterface;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\FakeLinkProvider;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\InMemoryQrCodeStorage;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;

final class QrCodeNormalizerTest extends TestCase
{
    use SuluStubsTrait;

    private const PAGE = 'page-uuid';

    public function testListsOneLabelledFilePerFormat(): void
    {
        $qrCode = $this->createQrCode();
        $qrCode->setSize(600);

        $files = $this->createNormalizer()->normalizeFiles($qrCode);

        self::assertSame(['png', 'svg'], \array_column($files, 'id'));
        self::assertSame(['PNG', 'SVG'], \array_column($files, 'format'), 'The format must be readable: list actions are bare icons.');
        self::assertSame(['sulu_qr_code.file_usage_png', 'sulu_qr_code.file_usage_svg'], \array_column($files, 'usage'));
        self::assertSame(['600 × 600 px', 'sulu_qr_code.file_dimensions_vector'], \array_column($files, 'dimensions'));
    }

    public function testEachFileHasItsOwnDownloadAndPreviewUrl(): void
    {
        $qrCode = $this->createQrCode();

        [$png, $svg] = $this->createNormalizer()->normalizeFiles($qrCode);

        self::assertSame('/admin/api/qr-codes/' . $qrCode->getId() . '/image.png?download=1', $png['downloadUrl']);
        self::assertSame('/admin/api/qr-codes/' . $qrCode->getId() . '/image.svg?download=1', $svg['downloadUrl']);

        self::assertIsArray($svg['thumbnails']);
        self::assertIsString($svg['thumbnails']['sulu-40x40']);
        // A size is mandatory for the SVG preview: its natural size is the full image.
        self::assertStringContainsString('/image.svg?size=160&v=', $svg['thumbnails']['sulu-40x40']);
    }

    public function testOnlyLinksToImageSizesThatAreActuallyServed(): void
    {
        $qrCode = $this->createQrCode();
        $normalizer = $this->createNormalizer();

        $urls = \array_values($normalizer->normalizeComputed($qrCode)['thumbnails']);
        foreach ($normalizer->normalizeFiles($qrCode) as $file) {
            self::assertIsArray($file['thumbnails']);
            $urls = [...$urls, ...\array_values($file['thumbnails'])];
        }

        self::assertNotEmpty($urls);
        foreach ($urls as $url) {
            self::assertIsString($url);
            self::assertSame(1, \preg_match('/[?&]size=(\d+)/', $url, $matches), $url);
            self::assertContains((int) $matches[1], QrCodeImageManager::PREVIEW_SIZES, $url);
        }
    }

    public function testDescribesTheCurrentTarget(): void
    {
        $qrCode = $this->createQrCode();
        $qrCode->setLink('page', self::PAGE, 'fr', ['anchor' => 'hours']);

        $computed = $this->createNormalizer()->normalizeComputed($qrCode);

        self::assertSame('Market page - /fr/marche#hours', $computed['target']);
        self::assertSame('https://www.example.com/qr/abc234', $computed['publicUrl']);
    }

    public function testFlagsATargetThatIsNoLongerAvailable(): void
    {
        $qrCode = new QrCode('abc234', 'Market', 'page', 'deleted-page');
        $qrCode->setLink('page', 'deleted-page', 'fr');

        self::assertSame(
            'sulu_qr_code.target_unavailable',
            $this->createNormalizer()->normalizeComputed($qrCode)['target'],
        );
    }

    public function testFullRepresentationMatchesTheFormFields(): void
    {
        $qrCode = $this->createQrCode();
        $qrCode->setCreated(new \DateTimeImmutable('2026-11-23 10:00:00'));
        $qrCode->setChanged(new \DateTimeImmutable('2026-11-23 11:00:00'));

        $data = $this->createNormalizer()->normalize($qrCode);

        self::assertSame($qrCode->getId(), $data['id']);
        self::assertSame('abc234', $data['code']);
        self::assertSame(['provider' => 'page', 'href' => self::PAGE, 'locale' => 'fr'], $data['link']);
        self::assertTrue($data['transparentBackground']);
        self::assertSame('#000000', $data['foregroundColor']);
        self::assertNull($data['logo']);
        self::assertSame([], $data['tags']);
        self::assertSame(0, $data['scanCount']);
        self::assertNull($data['lastScannedAt']);
        self::assertSame('2026-11-23T10:00:00+00:00', $data['created']);
    }

    private function createQrCode(): QrCode
    {
        $qrCode = new QrCode('abc234', 'Market', 'page', self::PAGE);
        $qrCode->setLink('page', self::PAGE, 'fr');

        return $qrCode;
    }

    private function createNormalizer(): QrCodeNormalizer
    {
        $routes = new RouteCollection();
        $routes->add(PublicUrlGenerator::ROUTE_NAME, new Route('/qr/{code}'));
        $routes->add(QrCodeNormalizer::IMAGE_ROUTE, new Route('/admin/api/qr-codes/{id}/image.{imageFormat}'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext('', 'GET', 'admin.example.test', 'https'));

        $publicUrlGenerator = new PublicUrlGenerator($urlGenerator, 'https://www.example.com');

        return new QrCodeNormalizer(
            $publicUrlGenerator,
            new QrCodeImageManager(
                $publicUrlGenerator,
                $this->createStub(QrCodeRendererInterface::class),
                new InMemoryQrCodeStorage(),
                new LogoResolver($this->createStub(StorageInterface::class)),
            ),
            new TargetUrlResolver(
                TestFactory::linkProviderPool(['page' => new FakeLinkProvider(['fr' => [self::PAGE => '/fr/marche']], 'Market page')]),
                $this->createWebspaceManager(['fr']),
            ),
            $urlGenerator,
            new IdentityTranslator(),
        );
    }
}
