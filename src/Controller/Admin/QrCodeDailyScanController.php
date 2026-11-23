<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Controller\Admin;

use Islesurlasorguetourisme\SuluQrCodeBundle\Admin\QrCodeAdmin;
use Islesurlasorguetourisme\SuluQrCodeBundle\Api\ListFactory;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCodeDailyScan;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only list of the scans of a QR code, one row per day.
 */
final class QrCodeDailyScanController implements SecuredControllerInterface
{
    public function __construct(
        private readonly ListFactory $listFactory,
    ) {
    }

    public function cgetAction(string $qrCodeId, Request $request): Response
    {
        $list = $this->listFactory->create(QrCodeDailyScan::class, QrCodeAdmin::DAILY_SCANS_LIST_KEY);
        $list->listBuilder->where($list->field('qrCodeId'), $qrCodeId);

        if (!$request->query->has('sortBy')) {
            $list->listBuilder->sort($list->field('scanDate'), ListBuilderInterface::SORTORDER_DESC);
        }

        return new JsonResponse($list->toRepresentation($list->getRows(), QrCodeDailyScan::RESOURCE_KEY));
    }

    public function getSecurityContext(): string
    {
        return QrCodeAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return $request->query->get('locale');
    }
}
