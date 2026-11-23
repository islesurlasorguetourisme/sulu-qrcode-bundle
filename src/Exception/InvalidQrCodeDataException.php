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
 * The data submitted for a QR code is not acceptable.
 *
 * It carries a translation key of the "admin" domain rather than a message,
 * so that the administration can show the reason in the language of the editor.
 */
final class InvalidQrCodeDataException extends \InvalidArgumentException
{
    /**
     * @param array<string, string|int|float> $translationParameters Keyed by placeholder, braces included (e.g. "{min}")
     */
    public function __construct(
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
        private readonly ?string $field = null,
    ) {
        parent::__construct($translationKey);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    public function getField(): ?string
    {
        return $this->field;
    }
}
