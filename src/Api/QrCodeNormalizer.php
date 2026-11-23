<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Api;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Generator\PublicUrlGenerator;
use Islesurlasorguetourisme\SuluQrCodeBundle\Image\QrCodeImageManager;
use Islesurlasorguetourisme\SuluQrCodeBundle\Link\TargetUrlResolver;
use Islesurlasorguetourisme\SuluQrCodeBundle\Renderer\ImageFormat;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the JSON representation of a QR code used by the administration.
 */
final class QrCodeNormalizer
{
    public const IMAGE_ROUTE = 'sulu_qr_code.get_qr_code_image';

    /**
     * Keys are the image formats Sulu's list components look for; values are rendered widths
     * (twice the displayed size, for high density screens).
     */
    private const THUMBNAILS = [
        'sulu-40x40' => QrCodeImageManager::THUMBNAIL_SIZE,
        'sulu-240x' => QrCodeImageManager::LARGE_THUMBNAIL_SIZE,
    ];

    public function __construct(
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly QrCodeImageManager $imageManager,
        private readonly TargetUrlResolver $targetUrlResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Full representation, in the shape expected by the "qr_code_details" form.
     *
     * @return array<string, mixed>
     */
    public function normalize(QrCode $qrCode, ?string $locale = null): array
    {
        $tagIds = [];
        foreach ($qrCode->getTags() as $tag) {
            $tagIds[] = $tag->getId();
        }

        return [
            'id' => $qrCode->getId(),
            'code' => $qrCode->getCode(),
            'title' => $qrCode->getTitle(),
            'link' => $qrCode->getLink(),
            'followVisitorLocale' => $qrCode->isFollowVisitorLocale(),
            'tags' => $tagIds,
            'foregroundColor' => $qrCode->getForegroundColor(),
            'backgroundColor' => $qrCode->getBackgroundColor(),
            'transparentBackground' => $qrCode->isTransparentBackground(),
            'size' => $qrCode->getSize(),
            'margin' => $qrCode->getMargin(),
            'errorCorrectionLevel' => $qrCode->getErrorCorrectionLevel(),
            'logo' => null !== $qrCode->getLogo() ? ['id' => $qrCode->getLogo()->getId()] : null,
            'logoSize' => $qrCode->getLogoSize(),
            'scanCount' => $qrCode->getScanCount(),
            'lastScannedAt' => $qrCode->getLastScannedAt()?->format(\DATE_ATOM),
            'created' => $qrCode->getCreated()->format(\DATE_ATOM),
            'changed' => $qrCode->getChanged()->format(\DATE_ATOM),
            ...$this->normalizeComputed($qrCode, $locale),
        ];
    }

    /**
     * Values that are not stored but derived from the QR code: URLs and the state of its target.
     * They are also merged into the rows of the list.
     *
     * @return array<string, mixed>
     */
    public function normalizeComputed(QrCode $qrCode, ?string $locale = null): array
    {
        $fingerprint = $this->imageManager->getFingerprint($qrCode);

        $thumbnails = [];
        foreach (self::THUMBNAILS as $key => $size) {
            $thumbnails[$key] = $this->generateImageUrl($qrCode, ImageFormat::PNG, ['size' => $size, 'v' => $fingerprint]);
        }

        return [
            'publicUrl' => $this->publicUrlGenerator->generate($qrCode->getCode()),
            'target' => $this->describeTarget($qrCode, $locale),
            'thumbnails' => $thumbnails,
            'downloadPngUrl' => $this->generateImageUrl($qrCode, ImageFormat::PNG, ['download' => 1]),
            'downloadSvgUrl' => $this->generateImageUrl($qrCode, ImageFormat::SVG, ['download' => 1]),
        ];
    }

    /**
     * One row per downloadable file, for the "Downloads" tab of a QR code.
     *
     * Formats are spelled out here because the administration can only attach bare icons
     * to list rows: an icon alone cannot tell a PNG from an SVG.
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeFiles(QrCode $qrCode, ?string $locale = null): array
    {
        $fingerprint = $this->imageManager->getFingerprint($qrCode);
        $files = [];

        foreach (ImageFormat::cases() as $format) {
            $files[] = [
                'id' => $format->value,
                'format' => \strtoupper($format->value),
                'usage' => $this->translator->trans(match ($format) {
                    ImageFormat::PNG => 'sulu_qr_code.file_usage_png',
                    ImageFormat::SVG => 'sulu_qr_code.file_usage_svg',
                }, [], 'admin', $locale),
                'dimensions' => ImageFormat::SVG === $format
                    ? $this->translator->trans('sulu_qr_code.file_dimensions_vector', [], 'admin', $locale)
                    : \sprintf('%1$d × %1$d px', $qrCode->getSize()),
                'thumbnails' => [
                    'sulu-40x40' => $this->generateImageUrl($qrCode, $format, ['size' => QrCodeImageManager::PREVIEW_SIZE, 'v' => $fingerprint]),
                ],
                'downloadUrl' => $this->generateImageUrl($qrCode, $format, ['download' => 1]),
            ];
        }

        return $files;
    }

    /**
     * A human readable description of where the QR code currently leads.
     */
    private function describeTarget(QrCode $qrCode, ?string $locale): string
    {
        try {
            $item = $this->targetUrlResolver->resolveItem($qrCode);
        } catch (\Throwable) {
            $item = null;
        }

        if (null === $item) {
            return $this->translator->trans('sulu_qr_code.target_unavailable', [], 'admin', $locale);
        }

        $url = TargetUrlResolver::appendQueryAndAnchor($item->getUrl(), $qrCode->getLinkData());
        $title = \trim($item->getTitle());

        return '' !== $title && $title !== $item->getUrl() ? $title . ' - ' . $url : $url;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function generateImageUrl(QrCode $qrCode, ImageFormat $format, array $parameters): string
    {
        return $this->urlGenerator->generate(self::IMAGE_ROUTE, [
            'id' => $qrCode->getId(),
            'imageFormat' => $format->value,
            ...$parameters,
        ]);
    }
}
