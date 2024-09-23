<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types = 1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\Framework\GraphQl\Query\Resolver\TypeResolverInterface;
use MageWorx\SeoRedirectsGraphQl\Model\Source\CustomRedirectType;

class CustomUrlTypeResolver implements TypeResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function resolveType(array $data) : string
    {
        if (isset($data['type_id']) && $data['type_id'] === CustomRedirectType::TYPE_CUSTOM) {
            return 'CustomUrlRedirect';
        }
        return '';
    }
}
