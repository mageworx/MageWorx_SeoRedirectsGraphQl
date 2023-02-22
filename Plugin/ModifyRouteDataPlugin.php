<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Plugin;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use MageWorx\SeoRedirectsGraphQl\Model\DpRedirectDataProviderInterface;
use MageWorx\SeoRedirectsGraphQl\Model\CustomRedirectDataProviderInterface;

class ModifyRouteDataPlugin
{
    /**
     * @var DpRedirectDataProviderInterface
     */
    protected $dpRedirectDataProvider;

    /**
     * @var CustomRedirectDataProviderInterface
     */
    protected $customRedirectDataProvider;

    /**
     * ModifyEntityUrlDataPlugin constructor.
     *
     * @param DpRedirectDataProviderInterface $dpRedirectDataProvider
     * @param CustomRedirectDataProviderInterface $customRedirectDataProvider
     */
    public function __construct(
        DpRedirectDataProviderInterface $dpRedirectDataProvider,
        CustomRedirectDataProviderInterface $customRedirectDataProvider
    ) {
        $this->dpRedirectDataProvider     = $dpRedirectDataProvider;
        $this->customRedirectDataProvider = $customRedirectDataProvider;
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
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        if (empty($result)) {
            if (!isset($args['url']) || empty(trim($args['url']))) {
                return $result;
            }

            $newData = $this->dpRedirectDataProvider->getRouteData($storeId, $args['url'], $info);
        } else {
            $objectId = empty($result['id']) ? null : $result['id'];
            $objectId = $objectId ?: (empty($result['entity_id']) ? null : $result['entity_id']);
            $objectId = $objectId ?: (empty($result['page_id']) ? null : $result['page_id']);

            if (empty($objectId) || empty($result['type'])) {
                return $result;
            }

            $newData = $this->customRedirectDataProvider->getRouteData(
                $storeId,
                (int)$objectId,
                $result['type'],
                $info
            );
        }

        return empty($newData) ? $result : $newData;
    }
}
