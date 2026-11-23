<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Unit\Entity;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class QrCodeTest extends TestCase
{
    public function testGetsATimeOrderedUuidAsIdentifier(): void
    {
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');

        self::assertInstanceOf(UuidV7::class, Uuid::fromString($qrCode->getId()));
        self::assertNotSame($qrCode->getId(), (new QrCode('def567', 'Other', 'external', 'https://example.org'))->getId());
    }

    public function testStartsWithNoScan(): void
    {
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');

        self::assertSame(0, $qrCode->getScanCount());
        self::assertNull($qrCode->getLastScannedAt());
    }

    public function testExposesTheLinkInTheShapeOfSulusLinkField(): void
    {
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');
        $qrCode->setLink('page', 'page-uuid', 'fr', ['anchor' => 'hours']);

        self::assertSame(
            ['anchor' => 'hours', 'provider' => 'page', 'href' => 'page-uuid', 'locale' => 'fr'],
            $qrCode->getLink(),
        );
    }

    public function testStoredLinkDataCannotOverrideTheTarget(): void
    {
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');
        $qrCode->setLink('page', 'page-uuid', 'fr', ['href' => 'tampered', 'provider' => 'tampered']);

        self::assertSame('page-uuid', $qrCode->getLink()['href']);
        self::assertSame('page', $qrCode->getLink()['provider']);
    }

    public function testSetTagsReplacesTheSelectionWithoutDuplicates(): void
    {
        $first = $this->createStub(TagInterface::class);
        $second = $this->createStub(TagInterface::class);
        $qrCode = new QrCode('abc234', 'Market', 'external', 'https://example.org');

        $qrCode->setTags([$first, $second, $first]);
        self::assertSame([$first, $second], $qrCode->getTags()->getValues());

        $qrCode->setTags([$second]);
        self::assertSame([$second], $qrCode->getTags()->getValues());
    }
}
