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
use MageWorx\SeoRedirectsGraphQl\Model\CustomRedirectDataProviderInterface;
use MageWorx\SeoRedirectsGraphQl\Model\DpRedirectDataProviderInterface;

class ModifyRouteDataPlugin
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
     * @param \Magento\UrlRewriteGraphQl\Model\Resolver\Route $subject
     * @param array|null $result
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed|Value
     */
    public function afterResolve(
        \Magento\UrlRewriteGraphQl\Model\Resolver\Route $subject,
                                                        $result,
        Field                                           $field,
                                                        $context,
        ResolveInfo                                     $info,
        array                                           $value = null,
        array                                           $args = null
    ) {
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        if ($result === null) {
            $result = [];
        }

        if (empty($result)) {
            if (!isset($args['url']) || empty(trim($args['url']))) { // @TODO: Check it
                return null;
            }

            $newData = $this->dpRedirectDataProvider->getRouteData($storeId, $args['url'], $info);

            if (!empty($newData)) {
                return $newData;
            }
        }

        // Regular custom redirect
        if ($this->resultHasId($result) && !empty($result['type'])) {
            $objectId = $this->extractIdFromResult($result);
            if ($objectId !== null) {
                $newData = $this->customRedirectDataProvider->getRouteData(
                    $storeId,
                    (int)$objectId,
                    $result['type'],
                    $info
                );

                if (!empty($newData)) {
                    return $newData;
                }
            }
        }

        // Trying to locate custom redirect from custom redirect to custom redirect, so much custom
        $customRedirect = $this->customRedirectFinder->getRedirectByPath($args['url'], [], $storeId);
        if ($customRedirect === null || $customRedirect->getId() === null) {
            return empty($result) ? null : $result;
        }

        $urlRewriteData = $this->customRedirectDataProvider->getEntityUrlDataByCustomRedirectEntity($customRedirect);

        return empty($urlRewriteData) ? null : $urlRewriteData;
    }

    /**
     * Is id in some form exists in the result array
     *
     * @param array $result
     * @return bool
     */
    public function resultHasId(array $result): bool
    {
        return !empty($result['id']) || !empty($result['entity_id']) || !empty($result['page_id']);
    }

    public function extractIdFromResult(array $result): ?int
    {
        $objectId = empty($result['id']) ? null : $result['id'];
        $objectId = $objectId ?: (empty($result['entity_id']) ? null : $result['entity_id']);
        $objectId = $objectId ?: (empty($result['page_id']) ? null : $result['page_id']);

        if ($objectId !== null) {
            $objectId = (int)$objectId;
        }

        return $objectId;
    }
}
