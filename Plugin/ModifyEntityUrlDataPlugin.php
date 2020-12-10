<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Plugin;

use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;
use MageWorx\SeoRedirects\Api\Data\CustomRedirectInterface;
use MageWorx\SeoRedirects\Helper\CustomRedirect\Data as CustomRedirectHelper;
use MageWorx\SeoRedirects\Helper\DpRedirect\Data as DpRedirectHelper;
use MageWorx\SeoRedirectsGraphQl\Model\Source\CustomRedirectType as CustomRedirectTypeOptions;
use MageWorx\SeoRedirects\Model\Redirect\CustomRedirect;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\Collection as CustomRedirectCollection;
use MageWorx\SeoRedirects\Model\Redirect\DpRedirect;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\DpRedirect\CollectionFactory as DpRedirectCollectionFactory;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\CollectionFactory
    as CustomRedirectCollectionFactory;
use MageWorx\SeoRedirects\Model\Redirect\Source\CustomRedirect\RedirectTypeRewriteFragment
    as RedirectTypeRewriteFragmentSource;

class ModifyEntityUrlDataPlugin
{
    /**
     * @var CustomRedirectHelper
     */
    protected $customRedirectHelper;

    /**
     * @var DpRedirectHelper
     */
    protected $dpRedirectHelper;

    /**
     * @var CustomRedirectCollectionFactory
     */
    protected $customRedirectCollectionFactory;

    /**
     * @var DpRedirectCollectionFactory
     */
    protected $dpRedirectCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var RedirectTypeRewriteFragmentSource
     */
    protected $redirectTypeRewriteFragmentSource;

    /**
     * @var CustomRedirectTypeOptions
     */
    protected $customRedirectTypeOptions;

    /**
     * @var CustomUrlLocatorInterface
     */
    protected $customUrlLocator;

    /**
     * ModifyEntityUrlDataPlugin constructor.
     *
     * @param CustomRedirectHelper $customRedirectHelper
     * @param DpRedirectHelper $dpRedirectHelper
     * @param CustomRedirectCollectionFactory $customRedirectCollectionFactory
     * @param DpRedirectCollectionFactory $dpRedirectCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource
     * @param UrlFinderInterface $urlFinder
     * @param CustomRedirectTypeOptions $customRedirectTypeOptions
     * @param CustomUrlLocatorInterface $customUrlLocator
     */
    public function __construct(
        CustomRedirectHelper $customRedirectHelper,
        DpRedirectHelper $dpRedirectHelper,
        CustomRedirectCollectionFactory $customRedirectCollectionFactory,
        DpRedirectCollectionFactory $dpRedirectCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource,
        UrlFinderInterface $urlFinder,
        CustomRedirectTypeOptions $customRedirectTypeOptions,
        CustomUrlLocatorInterface $customUrlLocator
    ) {
        $this->customRedirectHelper              = $customRedirectHelper;
        $this->dpRedirectHelper                  = $dpRedirectHelper;
        $this->customRedirectCollectionFactory   = $customRedirectCollectionFactory;
        $this->dpRedirectCollectionFactory       = $dpRedirectCollectionFactory;
        $this->categoryCollectionFactory         = $categoryCollectionFactory;
        $this->redirectTypeRewriteFragmentSource = $redirectTypeRewriteFragmentSource;
        $this->urlFinder                         = $urlFinder;
        $this->customRedirectTypeOptions         = $customRedirectTypeOptions;
        $this->customUrlLocator                  = $customUrlLocator;
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
     * @throws \Magento\Framework\Exception\LocalizedException
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

        if (empty($result)) {
            if (!isset($args['url']) || empty(trim($args['url']))) {
                return $result;
            }

            $newData = $this->getEntityUrlDataByDpRedirect($storeId, $args['url']);
        } else {
            if (empty($result['id']) || empty($result['type'])) {
                return $result;
            }

            $newData = $this->getEntityUrlDataByCustomRedirect($storeId, (int)$result['id'], $result['type']);
        }

        return empty($newData) ? $result : $newData;
    }

    /**
     * @param int $storeId
     * @param string $requestPath
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getEntityUrlDataByDpRedirect(int $storeId, string $requestPath)
    {
        if (!$this->dpRedirectHelper->isEnabled($storeId)) {
            return null;
        }

        if (substr($requestPath, 0, 1) === '/' && $requestPath !== '/') {
            $requestPath = ltrim($requestPath, '/');
        }

        $customUrl   = $this->customUrlLocator->locateUrl($requestPath);
        $requestPath = $customUrl ?: $requestPath;

        /** @var \MageWorx\SeoRedirects\Model\ResourceModel\Redirect\DpRedirect\Collection $dpRedirectCollection */
        $dpRedirectCollection = $this->dpRedirectCollectionFactory->create();
        $dpRedirectCollection
            ->addStoreFilter($storeId)
            ->addRequestPathsFilter($requestPath);

        /** @var DpRedirect $dpRedirect */
        $dpRedirect = $dpRedirectCollection->getFirstItem();

        if (!$dpRedirect->getCategoryId()) {
            return null;
        }

        $categoryIds = array_unique([$dpRedirect->getCategoryId(), $dpRedirect->getPriorityCategoryId()]);
        $collection  = $this->categoryCollectionFactory->create();
        $collection
            ->setStoreId($storeId)
            ->addAttributeToFilter('entity_id', $categoryIds)
            ->addFieldToFilter('is_active', ['eq' => 1]);

        $category = $this->getCategoryModel($collection, $dpRedirect);

        if (!$category) {
            return null;
        }

        $urlRewrite = $this->urlFinder->findOneByData(
            [
                UrlRewrite::ENTITY_ID   => $category->getId(),
                UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::STORE_ID    => $storeId,
            ]
        );

        if (!$urlRewrite) {
            return null;
        }

        $dpRedirect->setHits($dpRedirect->getHits() + 1);
        $dpRedirect->save();

        return [
            'id'            => $urlRewrite->getEntityId(),
            'canonical_url' => $urlRewrite->getRequestPath(),
            'relative_url'  => $urlRewrite->getRequestPath(),
            'redirectCode'  => (int)$this->dpRedirectHelper->getRedirectType(),
            'type'          => $this->sanitizeType($urlRewrite->getEntityType())
        ];
    }

    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @return array|null
     */
    protected function getEntityUrlDataByCustomRedirect(int $storeId, int $objectId, string $objectType)
    {
        if (!$this->customRedirectHelper->isEnabled($storeId)) {
            return null;
        }

        $redirectTypes = $this->customRedirectTypeOptions->getTypes();

        if (empty($redirectTypes[$objectType])) {
            return null;
        }

        /** @var CustomRedirectCollection $customRedirectCollection */
        $customRedirectCollection = $this->customRedirectCollectionFactory->create();
        $customRedirectCollection
            ->addStoreFilter($storeId)
            ->addFieldToFilter(CustomRedirectInterface::REQUEST_ENTITY_TYPE, $redirectTypes[$objectType])
            ->addFieldToFilter(CustomRedirectInterface::REQUEST_ENTITY_IDENTIFIER, $objectId)
            ->addFieldToFilter(CustomRedirectInterface::TARGET_ENTITY_TYPE, ['in' => array_values($redirectTypes)])
            ->addFieldToFilter(CustomRedirectInterface::STATUS, CustomRedirect::STATUS_ENABLED)
            ->addDateRangeFilter();

        /** @var \MageWorx\SeoRedirects\Model\Redirect\CustomRedirect $customRedirect */
        $customRedirect = $customRedirectCollection->getFirstItem();

        if (!$customRedirect->getId()) {
            return null;
        }

        $redirectTypeRewriteFragmentSource = $this->redirectTypeRewriteFragmentSource->toArray();

        if (empty($redirectTypeRewriteFragmentSource[$customRedirect->getTargetEntityType()])) {
            return null;
        }

        $targetPath = $redirectTypeRewriteFragmentSource[$customRedirect->getTargetEntityType(
            )] . $customRedirect->getTargetEntityIdentifier();
        $urlRewrite = $this->getRewriteByTargetPath($targetPath, (int)$customRedirect->getStoreId());

        if (!$urlRewrite) {
            return null;
        }

        return [
            'id'            => $urlRewrite->getEntityId(),
            'canonical_url' => $urlRewrite->getRequestPath(),
            'relative_url'  => $urlRewrite->getRequestPath(),
            'redirectCode'  => (int)$customRedirect->getRedirectCode(),
            'type'          => $this->sanitizeType($urlRewrite->getEntityType())
        ];
    }

    /**
     * @param string $targetPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    protected function getRewriteByTargetPath(string $targetPath, int $storeId)
    {
        return $this->urlFinder->findOneByData(
            [
                UrlRewrite::TARGET_PATH => trim($targetPath, '/'),
                UrlRewrite::STORE_ID    => $storeId,
            ]
        );
    }

    /**
     * @param CategoryCollection $collection
     * @param DpRedirect $redirect
     * @return \Magento\Catalog\Model\Category|DataObject
     */
    protected function getCategoryModel(CategoryCollection $collection, DpRedirect $redirect)
    {
        if ($collection->count() < 2) {
            return $collection->getFirstItem();
        }

        $isUsePriorityCategory = $this->dpRedirectHelper->isForceProductRedirectByPriority(
            (int)$redirect->getStoreId()
        );

        foreach ($collection as $item) {
            if ($isUsePriorityCategory) {
                if ($redirect->getPriorityCategoryId() == $item->getId()) {
                    return $item;
                }
            } else {
                if ($redirect->getCategoryId() == $item->getId()) {
                    return $item;
                }
            }
        }
    }

    /**
     * Sanitize the type to fit schema specifications
     *
     * @param string $type
     * @return string
     */
    protected function sanitizeType(string $type): string
    {
        return strtoupper(str_replace('-', '_', $type));
    }
}
