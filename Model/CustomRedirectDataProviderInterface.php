<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

interface CustomRedirectDataProviderInterface
{
    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @return array|null
     */
    public function getEntityUrlData(int $storeId, int $objectId, string $objectType): ?array;

    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @param ResolveInfo $info
     * @return array|null
     */
    public function getRouteData(int $storeId, int $objectId, string $objectType, ResolveInfo $info): ?array;
}
