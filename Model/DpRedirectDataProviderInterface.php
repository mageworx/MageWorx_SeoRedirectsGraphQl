<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

interface DpRedirectDataProviderInterface
{
    /**
     * @param int $storeId
     * @param string $requestPath
     * @return array|null
     */
    public function getEntityUrlData(int $storeId, string $requestPath): ?array;

    /**
     * @param int $storeId
     * @param string $requestPath
     * @param ResolveInfo $info
     * @return array|null
     */
    public function getRouteData(int $storeId, string $requestPath, ResolveInfo $info): ?array;
}
