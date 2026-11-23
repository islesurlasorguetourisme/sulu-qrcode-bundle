<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Exception;

/**
 * An image was requested in a size the bundle does not serve.
 */
final class UnsupportedImageSizeException extends \InvalidArgumentException
{
    /**
     * @param list<int> $supportedSizes
     */
    public function __construct(int $size, array $supportedSizes)
    {
        parent::__construct(\sprintf(
            'Images are not served in size %d. Supported sizes: %s.',
            $size,
            \implode(', ', $supportedSizes),
        ));
    }
}
