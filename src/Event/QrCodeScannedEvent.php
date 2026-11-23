<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Event;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched each time the public endpoint of a QR code is requested, right before the redirection.
 *
 * Listen to it to forward scans to an analytics tool, or to change the destination
 * with {@see QrCodeScannedEvent::setTargetUrl()}.
 */
final class QrCodeScannedEvent extends Event
{
    /**
     * @param string|null $targetUrl Where the visitor is about to be redirected; null when the target could not be resolved
     * @param bool $counted Whether the request was counted as a scan (false for robots)
     */
    public function __construct(
        private readonly QrCode $qrCode,
        private readonly Request $request,
        private ?string $targetUrl,
        private readonly bool $counted,
    ) {
    }

    public function getQrCode(): QrCode
    {
        return $this->qrCode;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function isCounted(): bool
    {
        return $this->counted;
    }
}
