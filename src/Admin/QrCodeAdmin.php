<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Islesurlasorguetourisme\SuluQrCodeBundle\Admin;

use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCode;
use Islesurlasorguetourisme\SuluQrCodeBundle\Entity\QrCodeDailyScan;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ListItemAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Registers the QR codes in the Sulu administration.
 *
 * Everything here relies on views, field types and actions shipped with Sulu:
 * the bundle does not require the administration JavaScript to be rebuilt.
 */
final class QrCodeAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu.qr_codes.qr_codes';

    public const LIST_VIEW = 'sulu_qr_code.list';
    public const ADD_FORM_VIEW = 'sulu_qr_code.add_form';
    public const ADD_FORM_DETAILS_VIEW = 'sulu_qr_code.add_form.details';
    public const EDIT_FORM_VIEW = 'sulu_qr_code.edit_form';
    public const EDIT_FORM_DETAILS_VIEW = 'sulu_qr_code.edit_form.details';
    public const EDIT_FORM_DOWNLOADS_VIEW = 'sulu_qr_code.edit_form.downloads';
    public const EDIT_FORM_STATISTICS_VIEW = 'sulu_qr_code.edit_form.statistics';

    public const LIST_KEY = 'qr_codes';
    public const FORM_KEY = 'qr_code_details';
    public const DAILY_SCANS_LIST_KEY = 'qr_code_daily_scans';
    public const FILES_RESOURCE_KEY = 'qr_code_files';
    public const FILES_LIST_KEY = 'qr_code_files';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $item = new NavigationItem('sulu_qr_code.qr_codes');
        $item->setIcon('fa-qrcode');
        $item->setPosition(45);
        $item->setView(self::LIST_VIEW);

        $navigationItemCollection->add($item);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $canAdd = $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD);
        $canEdit = $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT);
        $canDelete = $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::DELETE);

        $listToolbarActions = [];
        $formToolbarActions = [];

        if ($canAdd) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }

        if ($canAdd || $canEdit) {
            $formToolbarActions[] = new ToolbarAction('sulu_admin.save');
        }

        if ($canDelete) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
            $formToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        $listView = $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/qr-codes')
            ->setResourceKey(QrCode::RESOURCE_KEY)
            ->setListKey(self::LIST_KEY)
            ->setTitle('sulu_qr_code.qr_codes')
            ->addListAdapters(['table'])
            ->setEditView(self::EDIT_FORM_VIEW)
            ->addToolbarActions($listToolbarActions)
            ->addItemActions([
                // Item actions are bare icons: only one, unambiguous, download here (the PNG).
                // Every format is listed with a label in the "Downloads" tab of a QR code.
                new ListItemAction('link', ['icon' => 'su-download', 'link_property' => 'downloadPngUrl']),
            ]);

        if ($canAdd) {
            $listView->setAddView(self::ADD_FORM_VIEW);
        }

        $viewCollection->add($listView);

        if ($canAdd) {
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(self::ADD_FORM_VIEW, '/qr-codes/add')
                    ->setResourceKey(QrCode::RESOURCE_KEY)
                    ->setBackView(self::LIST_VIEW),
            );
            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(self::ADD_FORM_DETAILS_VIEW, '/details')
                    ->setResourceKey(QrCode::RESOURCE_KEY)
                    ->setFormKey(self::FORM_KEY)
                    ->setTabTitle('sulu_admin.details')
                    ->setEditView(self::EDIT_FORM_VIEW)
                    ->addToolbarActions($formToolbarActions)
                    ->setParent(self::ADD_FORM_VIEW),
            );
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(self::EDIT_FORM_VIEW, '/qr-codes/:id')
                ->setResourceKey(QrCode::RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('title'),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(self::EDIT_FORM_DETAILS_VIEW, '/details')
                ->setResourceKey(QrCode::RESOURCE_KEY)
                ->setFormKey(self::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setTabOrder(1024)
                ->addToolbarActions($formToolbarActions)
                ->setParent(self::EDIT_FORM_VIEW),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::EDIT_FORM_DOWNLOADS_VIEW, '/downloads')
                ->setResourceKey(self::FILES_RESOURCE_KEY)
                ->setListKey(self::FILES_LIST_KEY)
                ->setTabTitle('sulu_qr_code.downloads')
                ->setTabOrder(2048)
                ->addListAdapters(['table'])
                ->addRouterAttributesToListRequest(['id' => 'qrCodeId'])
                ->addItemActions([
                    new ListItemAction('link', ['icon' => 'su-download', 'link_property' => 'downloadUrl']),
                ])
                ->disableSearching()
                ->disableSelection()
                ->disableColumnOptions()
                ->disableFiltering()
                ->disablePagination()
                ->setParent(self::EDIT_FORM_VIEW),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::EDIT_FORM_STATISTICS_VIEW, '/statistics')
                ->setResourceKey(QrCodeDailyScan::RESOURCE_KEY)
                ->setListKey(self::DAILY_SCANS_LIST_KEY)
                ->setTabTitle('sulu_qr_code.statistics')
                ->setTabOrder(3072)
                ->addListAdapters(['table'])
                ->addRouterAttributesToListRequest(['id' => 'qrCodeId'])
                ->disableSearching()
                ->disableSelection()
                ->disableColumnOptions()
                ->disableFiltering()
                ->setParent(self::EDIT_FORM_VIEW),
        );
    }

    /**
     * @return array<string, array<string, array<string, list<string>>>>
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'QR codes' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
