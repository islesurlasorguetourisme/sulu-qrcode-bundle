<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Component\Persistence\Model\AuditableTrait;
use Symfony\Component\Uid\Uuid;

/**
 * A dynamic QR code.
 *
 * The printed image only ever encodes the public URL built from {@see QrCode::$code}.
 * Everything else (target, colours, logo…) can change without reprinting,
 * except what alters the image itself.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_qr_codes_code', columns: ['code'])]
class QrCode implements AuditableInterface
{
    use AuditableTrait;

    public const RESOURCE_KEY = 'qr_codes';
    public const TABLE_NAME = 'qr_codes';

    public const DEFAULT_FOREGROUND_COLOR = '#000000';
    public const DEFAULT_BACKGROUND_COLOR = '#ffffff';
    public const DEFAULT_SIZE = 1000;
    public const DEFAULT_MARGIN = 40;
    public const DEFAULT_ERROR_CORRECTION_LEVEL = 'M';
    public const DEFAULT_LOGO_SIZE = 20;

    public const MIN_SIZE = 100;
    public const MAX_SIZE = 4000;
    public const MIN_LOGO_SIZE = 10;
    public const MAX_LOGO_SIZE = 30;

    public const ERROR_CORRECTION_LEVELS = ['L', 'M', 'Q', 'H'];

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(name: 'code', type: Types::STRING, length: 32)]
    private string $code;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(name: 'link_provider', type: Types::STRING, length: 64)]
    private string $linkProvider;

    #[ORM\Column(name: 'link_href', type: Types::TEXT)]
    private string $linkHref;

    #[ORM\Column(name: 'link_locale', type: Types::STRING, length: 15, nullable: true)]
    private ?string $linkLocale = null;

    /**
     * Remaining keys of the Sulu link value (anchor, query, title, target, rel).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'link_data', type: Types::JSON)]
    private array $linkData = [];

    #[ORM\Column(name: 'follow_visitor_locale', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $followVisitorLocale = true;

    #[ORM\Column(name: 'foreground_color', type: Types::STRING, length: 7)]
    private string $foregroundColor = self::DEFAULT_FOREGROUND_COLOR;

    #[ORM\Column(name: 'background_color', type: Types::STRING, length: 7)]
    private string $backgroundColor = self::DEFAULT_BACKGROUND_COLOR;

    #[ORM\Column(name: 'transparent_background', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $transparentBackground = true;

    #[ORM\Column(name: 'size', type: Types::INTEGER)]
    private int $size = self::DEFAULT_SIZE;

    #[ORM\Column(name: 'margin', type: Types::INTEGER)]
    private int $margin = self::DEFAULT_MARGIN;

    #[ORM\Column(name: 'error_correction_level', type: Types::STRING, length: 1)]
    private string $errorCorrectionLevel = self::DEFAULT_ERROR_CORRECTION_LEVEL;

    #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(name: 'logo_media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MediaInterface $logo = null;

    /**
     * Logo width, in percent of the QR code width.
     */
    #[ORM\Column(name: 'logo_size', type: Types::INTEGER)]
    private int $logoSize = self::DEFAULT_LOGO_SIZE;

    /**
     * @var Collection<int, TagInterface>
     */
    #[ORM\ManyToMany(targetEntity: TagInterface::class)]
    #[ORM\JoinTable(name: 'qr_code_tags')]
    #[ORM\JoinColumn(name: 'qr_code_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $tags;

    /**
     * Denormalised total, maintained by the scan recorder with atomic SQL updates.
     */
    #[ORM\Column(name: 'scan_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $scanCount = 0;

    #[ORM\Column(name: 'last_scanned_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastScannedAt = null;

    public function __construct(string $code, string $title, string $linkProvider, string $linkHref, ?string $id = null)
    {
        $this->id = $id ?? Uuid::v7()->toRfc4122();
        $this->code = $code;
        $this->title = $title;
        $this->linkProvider = $linkProvider;
        $this->linkHref = $linkHref;
        $this->tags = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLinkProvider(): string
    {
        return $this->linkProvider;
    }

    public function getLinkHref(): string
    {
        return $this->linkHref;
    }

    public function getLinkLocale(): ?string
    {
        return $this->linkLocale;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLinkData(): array
    {
        return $this->linkData;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setLink(string $provider, string $href, ?string $locale = null, array $data = []): static
    {
        $this->linkProvider = $provider;
        $this->linkHref = $href;
        $this->linkLocale = $locale;
        $this->linkData = $data;

        return $this;
    }

    /**
     * The link in the shape expected by Sulu's "link" field type.
     *
     * @return array<string, mixed>
     */
    public function getLink(): array
    {
        return [
            ...$this->linkData,
            'provider' => $this->linkProvider,
            'href' => $this->linkHref,
            'locale' => $this->linkLocale,
        ];
    }

    public function isFollowVisitorLocale(): bool
    {
        return $this->followVisitorLocale;
    }

    public function setFollowVisitorLocale(bool $followVisitorLocale): static
    {
        $this->followVisitorLocale = $followVisitorLocale;

        return $this;
    }

    public function getForegroundColor(): string
    {
        return $this->foregroundColor;
    }

    public function setForegroundColor(string $foregroundColor): static
    {
        $this->foregroundColor = $foregroundColor;

        return $this;
    }

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(string $backgroundColor): static
    {
        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    public function isTransparentBackground(): bool
    {
        return $this->transparentBackground;
    }

    public function setTransparentBackground(bool $transparentBackground): static
    {
        $this->transparentBackground = $transparentBackground;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function setMargin(int $margin): static
    {
        $this->margin = $margin;

        return $this;
    }

    public function getErrorCorrectionLevel(): string
    {
        return $this->errorCorrectionLevel;
    }

    public function setErrorCorrectionLevel(string $errorCorrectionLevel): static
    {
        $this->errorCorrectionLevel = $errorCorrectionLevel;

        return $this;
    }

    public function getLogo(): ?MediaInterface
    {
        return $this->logo;
    }

    public function setLogo(?MediaInterface $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getLogoSize(): int
    {
        return $this->logoSize;
    }

    public function setLogoSize(int $logoSize): static
    {
        $this->logoSize = $logoSize;

        return $this;
    }

    /**
     * @return Collection<int, TagInterface>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param iterable<TagInterface> $tags
     */
    public function setTags(iterable $tags): static
    {
        $this->tags->clear();
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }

        return $this;
    }

    public function getScanCount(): int
    {
        return $this->scanCount;
    }

    public function getLastScannedAt(): ?\DateTimeImmutable
    {
        return $this->lastScannedAt;
    }
}
