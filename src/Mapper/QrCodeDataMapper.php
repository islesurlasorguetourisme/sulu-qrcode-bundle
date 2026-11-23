<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Mapper;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Exception\InvalidQrCodeDataException;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\ShortCodeGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\LogoResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\HexColor;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;
use Sulu\Bundle\MediaBundle\Admin\MediaAdmin;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;

/**
 * Validates the data sent by the administration form and applies it to a QR code.
 */
final class QrCodeDataMapper
{
    /**
     * Below this WCAG contrast ratio between modules and background, scanners start to fail.
     */
    public const MIN_CONTRAST_RATIO = 2.5;

    private const EXTERNAL_PROVIDER = 'external';

    /**
     * Only schemes a phone can open from a scan; rules out "javascript:", "data:" and friends.
     */
    private const EXTERNAL_HREF_PATTERN = '#^(https?://[^\s/?\#]+\S*|mailto:\S+|tel:\S+)$#i';

    /**
     * Control characters and white space have no place in what ends up in a "Location" header.
     */
    private const UNSAFE_URL_CHARACTERS_PATTERN = '/[\x00-\x20\x7f]/';

    private const LOCALE_PATTERN = '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})?$/';

    private const MAX_EXTERNAL_HREF_LENGTH = 2048;
    private const MAX_INTERNAL_HREF_LENGTH = 255;

    /**
     * Optional parts of a Sulu link value, with their maximum length. Only "query" and "anchor" reach the URL.
     */
    private const LINK_DATA_MAX_LENGTHS = ['anchor' => 255, 'query' => 2048, 'title' => 255, 'target' => 32, 'rel' => 255];
    private const LINK_DATA_URL_PARTS = ['anchor', 'query'];

    public function __construct(
        private readonly ShortCodeGenerator $shortCodeGenerator,
        private readonly LinkProviderPoolInterface $linkProviderPool,
        private readonly TagManagerInterface $tagManager,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly LogoResolver $logoResolver,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidQrCodeDataException
     */
    public function create(array $data): QrCode
    {
        [$provider, $href, $locale, $linkData] = $this->extractLink($data);

        $qrCode = new QrCode($this->shortCodeGenerator->generate(), $this->extractTitle($data), $provider, $href);
        $qrCode->setLink($provider, $href, $locale, $linkData);

        $this->applyOptions($qrCode, $data);

        return $qrCode;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidQrCodeDataException
     */
    public function update(QrCode $qrCode, array $data): void
    {
        [$provider, $href, $locale, $linkData] = $this->extractLink($data);

        $qrCode->setTitle($this->extractTitle($data));
        $qrCode->setLink($provider, $href, $locale, $linkData);

        $this->applyOptions($qrCode, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyOptions(QrCode $qrCode, array $data): void
    {
        $foregroundColor = $this->extractColor($data, 'foregroundColor', QrCode::DEFAULT_FOREGROUND_COLOR);
        $backgroundColor = $this->extractColor($data, 'backgroundColor', QrCode::DEFAULT_BACKGROUND_COLOR);
        $transparentBackground = self::toBool($data['transparentBackground'] ?? true);

        if (!$transparentBackground && HexColor::contrastRatio($foregroundColor, $backgroundColor) < self::MIN_CONTRAST_RATIO) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.low_contrast', [], 'backgroundColor');
        }

        $size = $this->extractInt($data, 'size', QrCode::DEFAULT_SIZE, QrCode::MIN_SIZE, QrCode::MAX_SIZE, 'sulu_qr_code.error.size_out_of_range');
        $maxMargin = \intdiv($size, 4);
        $margin = $this->extractInt($data, 'margin', \min(QrCode::DEFAULT_MARGIN, $maxMargin), 0, $maxMargin, 'sulu_qr_code.error.margin_out_of_range');

        $errorCorrectionLevel = \strtoupper((string) ($data['errorCorrectionLevel'] ?? '')) ?: QrCode::DEFAULT_ERROR_CORRECTION_LEVEL;
        if (!\in_array($errorCorrectionLevel, QrCode::ERROR_CORRECTION_LEVELS, true)) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_error_correction_level', [], 'errorCorrectionLevel');
        }

        $qrCode
            ->setFollowVisitorLocale(self::toBool($data['followVisitorLocale'] ?? true))
            ->setForegroundColor($foregroundColor)
            ->setBackgroundColor($backgroundColor)
            ->setTransparentBackground($transparentBackground)
            ->setSize($size)
            ->setMargin($margin)
            ->setErrorCorrectionLevel($errorCorrectionLevel)
            ->setLogo($this->extractLogo($data, $qrCode->getLogo()))
            ->setLogoSize($this->extractInt($data, 'logoSize', QrCode::DEFAULT_LOGO_SIZE, QrCode::MIN_LOGO_SIZE, QrCode::MAX_LOGO_SIZE, 'sulu_qr_code.error.logo_size_out_of_range'))
            ->setTags($this->extractTags($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractTitle(array $data): string
    {
        $title = \trim((string) (\is_scalar($data['title'] ?? null) ? $data['title'] : ''));

        if ('' === $title) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.title_required', [], 'title');
        }

        return \mb_substr($title, 0, 255);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{string, string, string|null, array<string, mixed>}
     */
    private function extractLink(array $data): array
    {
        $link = $data['link'] ?? null;
        $provider = \is_array($link) && \is_string($link['provider'] ?? null) ? $link['provider'] : '';
        $href = \is_array($link) && \is_scalar($link['href'] ?? null) ? \trim((string) $link['href']) : '';

        if (!\is_array($link) || '' === $provider || '' === $href) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.link_required', [], 'link');
        }

        if (!$this->linkProviderPool->hasProvider($provider)) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.unknown_link_provider', ['{provider}' => $provider], 'link');
        }

        if (1 === \preg_match(self::UNSAFE_URL_CHARACTERS_PATTERN, $href)) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_link', [], 'link');
        }

        if (self::EXTERNAL_PROVIDER === $provider) {
            if (\strlen($href) > self::MAX_EXTERNAL_HREF_LENGTH || 1 !== \preg_match(self::EXTERNAL_HREF_PATTERN, $href)) {
                throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_external_url', [], 'link');
            }
        } elseif (\strlen($href) > self::MAX_INTERNAL_HREF_LENGTH) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_link', [], 'link');
        }

        $locale = \is_string($link['locale'] ?? null) && '' !== $link['locale'] ? $link['locale'] : null;
        if (null !== $locale && 1 !== \preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_link', [], 'link');
        }

        $linkData = [];
        foreach (self::LINK_DATA_MAX_LENGTHS as $key => $maxLength) {
            $value = $link[$key] ?? null;
            if (!\is_string($value) || '' === $value) {
                continue;
            }

            $isUrlPart = \in_array($key, self::LINK_DATA_URL_PARTS, true);
            if (\strlen($value) > $maxLength || ($isUrlPart && 1 === \preg_match(self::UNSAFE_URL_CHARACTERS_PATTERN, $value))) {
                throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_link', [], 'link');
            }

            $linkData[$key] = $value;
        }

        return [$provider, $href, $locale, $linkData];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractColor(array $data, string $field, string $default): string
    {
        $value = $data[$field] ?? null;

        if (null === $value || '' === $value) {
            return $default;
        }

        $color = \is_string($value) ? HexColor::normalize($value) : null;

        if (null === $color) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.invalid_color', [], $field);
        }

        return $color;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractInt(array $data, string $field, int $default, int $min, int $max, string $errorKey): int
    {
        $value = $data[$field] ?? null;

        if (null === $value || '' === $value) {
            return $default;
        }

        if (!\is_numeric($value) || (int) $value < $min || (int) $value > $max) {
            throw new InvalidQrCodeDataException($errorKey, ['{min}' => $min, '{max}' => $max], $field);
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractLogo(array $data, ?MediaInterface $currentLogo): ?MediaInterface
    {
        $logo = $data['logo'] ?? null;
        $mediaId = \is_array($logo) ? ($logo['id'] ?? null) : $logo;

        if (null === $mediaId || '' === $mediaId || !\is_numeric($mediaId)) {
            return null;
        }

        // Unchanged logo: whoever chose it was allowed to. An editor without access to that
        // collection must still be able to save the other fields of the QR code.
        if (null !== $currentLogo && $currentLogo->getId() === (int) $mediaId) {
            return $currentLogo;
        }

        $media = $this->mediaRepository->findMediaById((int) $mediaId);

        if (!$media instanceof MediaInterface) {
            return null;
        }

        // The logo ends up inside the downloadable image: choosing a media as logo is a way of
        // reading it, so it requires the same permission as viewing it in the media library.
        $canView = $this->securityChecker->hasPermission(
            new SecurityCondition(MediaAdmin::SECURITY_CONTEXT, null, Collection::class, $media->getCollection()->getId()),
            PermissionTypes::VIEW,
        );

        if (!$canView) {
            throw new InvalidQrCodeDataException('sulu_qr_code.error.logo_not_accessible', [], 'logo');
        }

        if (!$this->logoResolver->isSupported($media)) {
            throw new InvalidQrCodeDataException(
                'sulu_qr_code.error.unsupported_logo',
                ['{maxSize}' => (int) (LogoResolver::MAX_FILE_SIZE / 1_000_000)],
                'logo',
            );
        }

        return $media;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<TagInterface>
     */
    private function extractTags(array $data): array
    {
        $tags = [];
        $values = \is_array($data['tags'] ?? null) ? $data['tags'] : [];

        foreach ($values as $value) {
            // Existing tags only, by id. Creating a tag is the job of the tag API, which checks
            // the permission to do so: accepting names here would be a way around it.
            $tag = \is_int($value) || (\is_string($value) && \ctype_digit($value))
                ? $this->tagManager->findById((int) $value)
                : null;

            if ($tag instanceof TagInterface) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private static function toBool(mixed $value): bool
    {
        return \filter_var($value, \FILTER_VALIDATE_BOOL);
    }
}
