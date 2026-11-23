<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\File;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * Stubs of Sulu services and entities, for PHPUnit test cases.
 */
trait SuluStubsTrait
{
    /**
     * @param list<string> $locales
     */
    private function createWebspaceManager(array $locales): WebspaceManagerInterface
    {
        $localizations = [];
        foreach ($locales as $locale) {
            [$language, $country] = \array_pad(\explode('_', $locale, 2), 2, null);
            $localizations[$locale] = new Localization($language, $country);
        }

        $webspaceManager = $this->createStub(WebspaceManagerInterface::class);
        $webspaceManager->method('getAllLocalesByWebspaces')->willReturn(['website' => $localizations]);

        return $webspaceManager;
    }

    /**
     * A media made of a single file of the given type, stored in the given collection.
     */
    private function createMedia(int $id, string $mimeType, int $version = 1, int $fileSize = 1, int $collectionId = 1): MediaInterface
    {
        $fileVersion = (new FileVersion())
            ->setName('logo')
            ->setVersion($version)
            ->setSize($fileSize)
            ->setMimeType($mimeType)
            ->setStorageOptions(['segment' => '01', 'fileName' => 'logo']);

        $file = (new File())->setVersion($version)->addFileVersion($fileVersion);

        $media = $this->createStub(MediaInterface::class);
        $media->method('getId')->willReturn($id);
        $media->method('getFiles')->willReturn(new ArrayCollection([$file]));

        $collection = $this->createStub(CollectionInterface::class);
        $collection->method('getId')->willReturn($collectionId);
        $media->method('getCollection')->willReturn($collection);

        return $media;
    }
}
