<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model\Source;

use MageWorx\SeoRedirects\Model\Redirect\CustomRedirect;

class CustomRedirectType
{
    const TYPE_PRODUCT  = 'PRODUCT';
    const TYPE_CATEGORY = 'CATEGORY';
    const TYPE_CMS_PAGE = 'CMS_PAGE';

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return [
            self::TYPE_PRODUCT  => CustomRedirect::REDIRECT_TYPE_PRODUCT,
            self::TYPE_CATEGORY => CustomRedirect::REDIRECT_TYPE_CATEGORY,
            self::TYPE_CMS_PAGE => CustomRedirect::REDIRECT_TYPE_PAGE
        ];
    }
}
