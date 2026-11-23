<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Api;

use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;

/**
 * Builds Sulu list builders from the list definitions of the bundle (config/lists/*.xml).
 *
 * Paging, sorting, searching and filtering are read from the current request by Sulu.
 */
final class ListFactory
{
    public function __construct(
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
    ) {
    }

    /**
     * @param class-string $entityClass
     */
    public function create(string $entityClass, string $listKey): PreparedList
    {
        $fieldDescriptors = [];

        foreach ($this->fieldDescriptorFactory->getFieldDescriptors($listKey) ?? [] as $name => $fieldDescriptor) {
            if ($fieldDescriptor instanceof DoctrineFieldDescriptorInterface) {
                $fieldDescriptors[(string) $name] = $fieldDescriptor;
            }
        }

        if ([] === $fieldDescriptors) {
            throw new \LogicException(\sprintf('No list definition found for "%s". Is the "config/lists" directory of the bundle registered in "sulu_admin.lists.directories"?', $listKey));
        }

        $listBuilder = $this->listBuilderFactory->create($entityClass);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $list = new PreparedList($listBuilder, $fieldDescriptors, $listKey);
        $listBuilder->setIdField($list->field('id'));

        return $list;
    }
}
