<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Mapper;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\InvalidQrCodeDataException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\ShortCodeGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Mapper\QrCodeDataMapper;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\FakeLinkProvider;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\InMemoryQrCodeRepository;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\SuluStubsTrait;
use Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;

final class QrCodeDataMapperTest extends TestCase
{
    use SuluStubsTrait;

    /**
     * @var list<array{mixed, string}>
     */
    private array $checkedPermissions = [];

    private const VALID = [
        'title' => 'Sunday market poster',
        'link' => ['provider' => 'external', 'href' => 'https://example.org/market', 'locale' => 'fr'],
    ];

    public function testCreateAppliesTheDocumentedDefaults(): void
    {
        $qrCode = $this->createMapper()->create(self::VALID);

        self::assertSame('Sunday market poster', $qrCode->getTitle());
        self::assertSame(ShortCodeGenerator::LENGTH, \strlen($qrCode->getCode()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $qrCode->getId(), 'The id is a UUID v7.');
        self::assertSame('#000000', $qrCode->getForegroundColor());
        self::assertSame('#ffffff', $qrCode->getBackgroundColor());
        self::assertTrue($qrCode->isTransparentBackground(), 'Black on transparent is the default.');
        self::assertTrue($qrCode->isFollowVisitorLocale());
        self::assertSame(1000, $qrCode->getSize());
        self::assertSame(40, $qrCode->getMargin());
        self::assertSame('M', $qrCode->getErrorCorrectionLevel());
        self::assertSame(20, $qrCode->getLogoSize());
        self::assertNull($qrCode->getLogo());
        self::assertCount(0, $qrCode->getTags());
    }

    public function testEmptyFormValuesFallBackToDefaults(): void
    {
        $qrCode = $this->createMapper()->create([
            ...self::VALID,
            'foregroundColor' => null,
            'backgroundColor' => '',
            'size' => null,
            'margin' => '',
            'errorCorrectionLevel' => null,
            'logo' => ['id' => null, 'displayOption' => null],
            'logoSize' => null,
            'tags' => null,
        ]);

        self::assertSame('#000000', $qrCode->getForegroundColor());
        self::assertSame(1000, $qrCode->getSize());
        self::assertSame(40, $qrCode->getMargin());
        self::assertNull($qrCode->getLogo());
    }

    public function testStoresTheLinkWithItsOptionalParts(): void
    {
        $qrCode = $this->createMapper()->create([
            'title' => 'Market',
            'link' => [
                'provider' => 'page',
                'href' => 'page-uuid',
                'locale' => 'fr',
                'anchor' => 'hours',
                'query' => 'utm_source=qr',
                'title' => null,
                'unexpected' => 'dropped',
            ],
        ]);

        self::assertSame(
            ['anchor' => 'hours', 'query' => 'utm_source=qr', 'provider' => 'page', 'href' => 'page-uuid', 'locale' => 'fr'],
            $qrCode->getLink(),
        );
    }

    public function testUpdateChangesTheTargetButNeverTheCode(): void
    {
        $mapper = $this->createMapper();
        $qrCode = $mapper->create(self::VALID);
        $code = $qrCode->getCode();
        $id = $qrCode->getId();

        $mapper->update($qrCode, [
            'title' => 'Renamed',
            'code' => 'hacked',
            'id' => 'hacked',
            'link' => ['provider' => 'page', 'href' => 'page-uuid', 'locale' => 'en'],
            'transparentBackground' => false,
            'foregroundColor' => '#0B3D91',
            'backgroundColor' => '#fff4d6',
            'size' => 600,
            'margin' => 30,
            'errorCorrectionLevel' => 'q',
            'followVisitorLocale' => false,
        ]);

        self::assertSame($code, $qrCode->getCode(), 'Printed QR codes must keep working.');
        self::assertSame($id, $qrCode->getId());
        self::assertSame('Renamed', $qrCode->getTitle());
        self::assertSame('page', $qrCode->getLinkProvider());
        self::assertSame('page-uuid', $qrCode->getLinkHref());
        self::assertSame('en', $qrCode->getLinkLocale());
        self::assertSame('#0b3d91', $qrCode->getForegroundColor());
        self::assertFalse($qrCode->isTransparentBackground());
        self::assertFalse($qrCode->isFollowVisitorLocale());
        self::assertSame(600, $qrCode->getSize());
        self::assertSame(30, $qrCode->getMargin());
        self::assertSame('Q', $qrCode->getErrorCorrectionLevel());
    }

    public function testDefaultMarginNeverExceedsAQuarterOfASmallImage(): void
    {
        $qrCode = $this->createMapper()->create([...self::VALID, 'size' => 100]);

        self::assertSame(25, $qrCode->getMargin());
    }

    public function testResolvesTagsByIdAndIgnoresUnknownOnes(): void
    {
        $tag = $this->createStub(TagInterface::class);
        $tagManager = $this->createStub(TagManagerInterface::class);
        $tagManager->method('findById')->willReturnCallback(static fn (int $id): ?TagInterface => 7 === $id ? $tag : null);

        $qrCode = $this->createMapper(tagManager: $tagManager)->create([...self::VALID, 'tags' => [7, 404, 7]]);

        self::assertSame([$tag], $qrCode->getTags()->toArray());
    }

    public function testNeverCreatesTagsFromNames(): void
    {
        // Creating a tag is guarded by its own permission in Sulu: this endpoint must not be a way around it.
        $tagManager = $this->createMock(TagManagerInterface::class);
        $tagManager->expects(self::never())->method('findOrCreateByName');
        $tagManager->expects(self::never())->method('save');
        $tagManager->method('findById')->willReturn(null);

        $qrCode = $this->createMapper(tagManager: $tagManager)->create([...self::VALID, 'tags' => ['new-tag', '12abc', ['nested'], 1.5, null]]);

        self::assertCount(0, $qrCode->getTags());
    }

    public function testChoosingALogoRequiresViewPermissionOnItsCollection(): void
    {
        $mapper = $this->createMapper(media: $this->createMedia(12, 'image/png', collectionId: 7), canViewMedia: false);

        try {
            $mapper->create([...self::VALID, 'logo' => ['id' => 12]]);
            self::fail('A media the user cannot view must not be usable as a logo.');
        } catch (InvalidQrCodeDataException $exception) {
            self::assertSame('sulu_qr_code.error.logo_not_accessible', $exception->getTranslationKey());
        }

        self::assertCount(1, $this->checkedPermissions);
        [$condition, $permission] = $this->checkedPermissions[0];
        self::assertInstanceOf(SecurityCondition::class, $condition);
        self::assertSame('sulu.media.collections', $condition->getSecurityContext());
        self::assertSame(7, $condition->getObjectId());
        self::assertSame(PermissionTypes::VIEW, $permission);
    }

    public function testKeepingTheCurrentLogoDoesNotRequireAccessToIt(): void
    {
        $media = $this->createMedia(12, 'image/png');
        $qrCode = $this->createMapper(media: $media)->create([...self::VALID, 'logo' => ['id' => 12]]);

        // Another editor, without access to the media library, changes the target.
        $this->createMapper(media: $media, canViewMedia: false)->update($qrCode, [
            'title' => 'Renamed',
            'link' => ['provider' => 'external', 'href' => 'https://example.org/new'],
            'logo' => ['id' => 12, 'displayOption' => null],
        ]);

        self::assertSame($media, $qrCode->getLogo());
        self::assertSame('https://example.org/new', $qrCode->getLinkHref());
    }

    public function testRejectsALogoFileThatIsTooBig(): void
    {
        $mapper = $this->createMapper(media: $this->createMedia(12, 'image/png', fileSize: LogoResolver::MAX_FILE_SIZE + 1));

        $this->expectException(InvalidQrCodeDataException::class);
        $this->expectExceptionMessage('sulu_qr_code.error.unsupported_logo');

        $mapper->create([...self::VALID, 'logo' => ['id' => 12]]);
    }

    public function testAcceptsARasterLogo(): void
    {
        $media = $this->createMedia(12, 'image/png');

        $qrCode = $this->createMapper(media: $media)->create([...self::VALID, 'logo' => ['id' => 12], 'logoSize' => 25]);

        self::assertSame($media, $qrCode->getLogo());
        self::assertSame(25, $qrCode->getLogoSize());
    }

    public function testRemovingTheLogo(): void
    {
        $mapper = $this->createMapper(media: $this->createMedia(12, 'image/png'));
        $qrCode = $mapper->create([...self::VALID, 'logo' => ['id' => 12]]);

        $mapper->update($qrCode, [...self::VALID, 'logo' => null]);

        self::assertNull($qrCode->getLogo());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function provideInvalidData(): iterable
    {
        yield 'missing title' => [['title' => '  '], 'sulu_qr_code.error.title_required', 'title'];
        yield 'missing link' => [['link' => null], 'sulu_qr_code.error.link_required', 'link'];
        yield 'link without target' => [['link' => ['provider' => 'page', 'href' => '']], 'sulu_qr_code.error.link_required', 'link'];
        yield 'unknown provider' => [['link' => ['provider' => 'nope', 'href' => '1']], 'sulu_qr_code.error.unknown_link_provider', 'link'];
        yield 'javascript url' => [['link' => ['provider' => 'external', 'href' => 'javascript:alert(1)']], 'sulu_qr_code.error.invalid_external_url', 'link'];
        yield 'data url' => [['link' => ['provider' => 'external', 'href' => 'data:text/html,<script>']], 'sulu_qr_code.error.invalid_external_url', 'link'];
        yield 'relative url' => [['link' => ['provider' => 'external', 'href' => '/just/a/path']], 'sulu_qr_code.error.invalid_external_url', 'link'];
        yield 'line break in external url' => [['link' => ['provider' => 'external', 'href' => "https://example.org/a\r\nX-Injected: 1"]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'space in external url' => [['link' => ['provider' => 'external', 'href' => 'https://example.org/a b']], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'null byte in page id' => [['link' => ['provider' => 'page', 'href' => "page\0uuid"]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'external url without host' => [['link' => ['provider' => 'external', 'href' => 'https:///path']], 'sulu_qr_code.error.invalid_external_url', 'link'];
        yield 'oversized external url' => [['link' => ['provider' => 'external', 'href' => 'https://example.org/' . \str_repeat('a', 2100)]], 'sulu_qr_code.error.invalid_external_url', 'link'];
        yield 'oversized internal id' => [['link' => ['provider' => 'page', 'href' => \str_repeat('a', 300)]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'line break in query' => [['link' => ['provider' => 'page', 'href' => 'page-uuid', 'query' => "a=1\r\nSet-Cookie: x=1"]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'line break in anchor' => [['link' => ['provider' => 'page', 'href' => 'page-uuid', 'anchor' => "top\nX: y"]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'oversized anchor' => [['link' => ['provider' => 'page', 'href' => 'page-uuid', 'anchor' => \str_repeat('a', 5000)]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'malformed locale' => [['link' => ['provider' => 'page', 'href' => 'page-uuid', 'locale' => \str_repeat('a', 40)]], 'sulu_qr_code.error.invalid_link', 'link'];
        yield 'invalid colour' => [['foregroundColor' => 'red'], 'sulu_qr_code.error.invalid_color', 'foregroundColor'];
        yield 'white on white' => [['transparentBackground' => false, 'foregroundColor' => '#ffffff', 'backgroundColor' => '#fefefe'], 'sulu_qr_code.error.low_contrast', 'backgroundColor'];
        yield 'too small' => [['size' => 50], 'sulu_qr_code.error.size_out_of_range', 'size'];
        yield 'too big' => [['size' => 10000], 'sulu_qr_code.error.size_out_of_range', 'size'];
        yield 'size is not a number' => [['size' => 'big'], 'sulu_qr_code.error.size_out_of_range', 'size'];
        yield 'margin eats the code' => [['size' => 400, 'margin' => 150], 'sulu_qr_code.error.margin_out_of_range', 'margin'];
        yield 'unknown error correction level' => [['errorCorrectionLevel' => 'X'], 'sulu_qr_code.error.invalid_error_correction_level', 'errorCorrectionLevel'];
        yield 'logo too large' => [['logoSize' => 60], 'sulu_qr_code.error.logo_size_out_of_range', 'logoSize'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('provideInvalidData')]
    public function testRejectsInvalidData(array $override, string $expectedKey, string $expectedField): void
    {
        try {
            $this->createMapper()->create([...self::VALID, ...$override]);
            self::fail('The data should have been rejected.');
        } catch (InvalidQrCodeDataException $exception) {
            self::assertSame($expectedKey, $exception->getTranslationKey());
            self::assertSame($expectedField, $exception->getField());
        }
    }

    public function testLowContrastIsOnlyCheckedAgainstAnOpaqueBackground(): void
    {
        $qrCode = $this->createMapper()->create([
            ...self::VALID,
            'transparentBackground' => true,
            'foregroundColor' => '#ffffff',
            'backgroundColor' => '#ffffff',
        ]);

        self::assertSame('#ffffff', $qrCode->getForegroundColor());
    }

    public function testRejectsAVectorLogo(): void
    {
        $mapper = $this->createMapper(media: $this->createMedia(12, 'image/svg+xml'));

        $this->expectException(InvalidQrCodeDataException::class);
        $this->expectExceptionMessage('sulu_qr_code.error.unsupported_logo');

        $mapper->create([...self::VALID, 'logo' => ['id' => 12]]);
    }

    public function testOutOfRangeErrorsCarryTheBoundsForTheMessage(): void
    {
        try {
            $this->createMapper()->create([...self::VALID, 'size' => 5]);
            self::fail('The data should have been rejected.');
        } catch (InvalidQrCodeDataException $exception) {
            self::assertSame(['{min}' => QrCode::MIN_SIZE, '{max}' => QrCode::MAX_SIZE], $exception->getTranslationParameters());
        }
    }

    private function createMapper(
        ?TagManagerInterface $tagManager = null,
        ?MediaInterface $media = null,
        bool $canViewMedia = true,
    ): QrCodeDataMapper {
        $mediaRepository = $this->createStub(MediaRepositoryInterface::class);
        $mediaRepository->method('findMediaById')->willReturn($media);

        $securityChecker = $this->createStub(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->willReturnCallback(
            function (mixed $subject, string $permission) use ($canViewMedia): bool {
                $this->checkedPermissions[] = [$subject, $permission];

                return $canViewMedia;
            },
        );

        return new QrCodeDataMapper(
            new ShortCodeGenerator(new InMemoryQrCodeRepository()),
            TestFactory::linkProviderPool([
                'external' => new FakeLinkProvider([]),
                'page' => new FakeLinkProvider([]),
            ]),
            $tagManager ?? $this->createStub(TagManagerInterface::class),
            $mediaRepository,
            new LogoResolver($this->createStub(StorageInterface::class)),
            $securityChecker,
        );
    }
}
