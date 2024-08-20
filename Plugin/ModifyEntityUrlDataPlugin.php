<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types = 1);

namespace MageWorx\SeoRedirectsGraphQl\Plugin;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MageWorx\SeoRedirects\Model\Redirect\CustomRedirectFinder;
use MageWorx\SeoRedirectsGraphQl\Model\DpRedirectDataProviderInterface;
use MageWorx\SeoRedirectsGraphQl\Model\CustomRedirectDataProviderInterface;

class ModifyEntityUrlDataPlugin
{
    protected DpRedirectDataProviderInterface     $dpRedirectDataProvider;
    protected CustomRedirectDataProviderInterface $customRedirectDataProvider;
    protected CustomRedirectFinder                $customRedirectFinder;

    public function __construct(
        DpRedirectDataProviderInterface     $dpRedirectDataProvider,
        CustomRedirectDataProviderInterface $customRedirectDataProvider,
        CustomRedirectFinder                $customRedirectFinder
    ) {
        $this->dpRedirectDataProvider     = $dpRedirectDataProvider;
        $this->customRedirectDataProvider = $customRedirectDataProvider;
        $this->customRedirectFinder       = $customRedirectFinder;
    }

    /**
     * @param \Magento\UrlRewriteGraphQl\Model\Resolver\EntityUrl $subject
     * @param array|null $result
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed|Value
     */
    public function afterResolve(
        $subject,
        $result,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        // Trying to locate DP redirect
        if (empty($result)) {
            if (!isset($args['url']) || empty(trim($args['url']))) {
                return $result;
            }

            $newData = $this->dpRedirectDataProvider->getEntityUrlData($storeId, $args['url']);
            if (!empty($newData)) {
                return $newData;
            }
        }

        // Trying to locate custom redirect
        $customRedirect = $this->customRedirectFinder->getRedirectByPath($args['url'], [], $storeId);
        if ($customRedirect->getId() === null) {
            return $result;
        }

        $urlRewriteData = $this->customRedirectDataProvider->getEntityUrlDataByCustomRedirectEntity($customRedirect);

        return empty($urlRewriteData) ? $result : $urlRewriteData;
    }
}
