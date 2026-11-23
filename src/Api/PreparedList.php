<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Api;

use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;

/**
 * A list builder already initialised from the current request, together with its field descriptors.
 */
final class PreparedList
{
    /**
     * @param array<string, DoctrineFieldDescriptorInterface> $fieldDescriptors
     */
    public function __construct(
        public readonly DoctrineListBuilder $listBuilder,
        private readonly array $fieldDescriptors,
        private readonly string $listKey,
    ) {
    }

    public function field(string $name): DoctrineFieldDescriptorInterface
    {
        return $this->fieldDescriptors[$name]
            ?? throw new \LogicException(\sprintf('The list "%s" has no property named "%s".', $this->listKey, $name));
    }

    /**
     * Runs the query and returns the rows, ready to be encoded as JSON.
     *
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        /** @var iterable<array<string, mixed>> $rows */
        $rows = $this->listBuilder->execute();

        return ListRowNormalizer::normalize($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public function toRepresentation(array $rows, string $resourceKey): array
    {
        return (new PaginatedRepresentation(
            $rows,
            $resourceKey,
            (int) $this->listBuilder->getCurrentPage(),
            (int) $this->listBuilder->getLimit(),
            (int) $this->listBuilder->count(),
        ))->toArray();
    }
}
