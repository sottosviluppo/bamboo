<?php

/*
 * This file is part of the Elcodi package.
 *
 * Copyright (c) 2014-2016 Elcodi.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 * @author Aldo Chiecchia <zimage@tiscali.it>
 * @author Elcodi Team <tech@elcodi.com>
 */

namespace Elcodi\Admin\CouponBundle\Builder;

use Elcodi\Component\Menu\Builder\Abstracts\AbstractMenuBuilder;
use Elcodi\Component\Menu\Builder\Interfaces\MenuBuilderInterface;
use Elcodi\Component\Menu\Entity\Menu\Interfaces\MenuInterface;
use Elcodi\Component\Menu\Factory\NodeFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MenuBuilder
 */
class MenuBuilder extends AbstractMenuBuilder implements MenuBuilderInterface
{
    private $permissionsRepository;
    private $currentUser;
    private $resource = "coupon";
    private $permissions = [
        'canRead' => false,
        'canCreate' => false,
        'canUpdate' => false,
        'canDelete' => false,
    ];

    public function __construct(NodeFactory $menuNodeFactory, ContainerInterface $container)
    {
        parent::__construct($menuNodeFactory);
        $this->permissionsRepository = $container->get('elcodi.repository.permission_group');
        $this->currentUser = $container->get('security.token_storage')->getToken()->getUser();

        $this->permissions = [
            'canRead' => $this->permissionsRepository->canReadEntity($this->resource, $this->currentUser),
            'canCreate' => $this->permissionsRepository->canCreateEntity($this->resource, $this->currentUser),
            'canUpdate' => $this->permissionsRepository->canUpdateEntity($this->resource, $this->currentUser),
            'canDelete' => $this->permissionsRepository->canDeleteEntity($this->resource, $this->currentUser),
        ];
    }

    /**
     * Build the menu
     *
     * @param MenuInterface $menu Menu
     */
    public function build(MenuInterface $menu)
    {
        if ($this->hasAnyPermission()) {
            $activeUrls = [];
            $node = $this
                ->menuNodeFactory
                ->create()
                ->setName('admin.coupon.plural')
                ->setCode('gift')
                ->setTag('catalog')
                ->setPriority(32);

            if ($this->permissions['canRead']) {
                $node->setUrl('admin_coupon_list');
            } elseif ($this->permissions['canCreate']) {
                $node->setUrl('admin_coupon_new');
            }

            if ($this->permissions['canCreate']) {
                $activeUrls[] = 'admin_coupon_new';
            }
            if ($this->permissions['canUpdate']) {
                $activeUrls[] = 'admin_coupon_edit';
            }

            if (!empty($activeUrls)) {
                $node->setActiveUrls($activeUrls);
            }

            $node
                ->addSubnode(
                    $this
                        ->menuNodeFactory
                        ->create()
                        ->setName('admin.coupon.plural')
                        ->setCode('file-text-o')
                        ->setUrl('admin_coupon_list')
                        ->setActiveUrls([
                            'admin_coupon_edit',
                            'admin_coupon_new',
                        ])
                )
                ->addSubnode(
                    $this
                        ->menuNodeFactory
                        ->create()
                        ->setName('admin.coupon_campaign.plural')
                        ->setCode('file-text-o')
                        ->setUrl('admin_coupon_campaign_list')
                        ->setActiveUrls([
                            'admin_coupon_campaign_edit',
                            'admin_coupon_campaign_new',
                        ])
                );

            $menu->addSubnode($node);
        }
    }

    private function hasAnyPermission()
    {
        foreach ($this->permissions as $key => $value) {
            if ($value) {
                return true;
            }
        }

        return false;
    }
}
