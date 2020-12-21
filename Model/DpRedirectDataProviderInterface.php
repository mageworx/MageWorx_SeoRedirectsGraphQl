<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

interface DpRedirectDataProviderInterface
{
    /**
     * @param int $storeId
     * @param string $requestPath
     * @return array|null
     */
    public function getEntityUrlData(int $storeId, string $requestPath): ?array;
}
