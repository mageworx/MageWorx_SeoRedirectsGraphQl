<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\Catalog\Api\Data\CategoryInterface as CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\DataObject;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;
use MageWorx\SeoRedirects\Helper\DpRedirect\Data as DpRedirectHelper;
use MageWorx\SeoRedirects\Model\Redirect\DpRedirect;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\DpRedirect\CollectionFactory as DpRedirectCollectionFactory;

class DpRedirectDataProvider implements DpRedirectDataProviderInterface
{
    /**
     * @var DpRedirectHelper
     */
    protected $dpRedirectHelper;

    /**
     * @var DpRedirectCollectionFactory
     */
    protected $dpRedirectCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var CustomUrlLocatorInterface
     */
    protected $customUrlLocator;

    /**
     * @var UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var Uid
     */
    protected $idEncoder;

    /**
     * DpRedirectDataProvider constructor.
     *
     * @param DpRedirectHelper $dpRedirectHelper
     * @param DpRedirectCollectionFactory $dpRedirectCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CustomUrlLocatorInterface $customUrlLocator
     * @param UrlFinderInterface $urlFinder
     * @param Uid $idEncoder
     */
    public function __construct(
        DpRedirectHelper $dpRedirectHelper,
        DpRedirectCollectionFactory $dpRedirectCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CustomUrlLocatorInterface $customUrlLocator,
        UrlFinderInterface $urlFinder,
        Uid $idEncoder
    ) {
        $this->dpRedirectHelper            = $dpRedirectHelper;
        $this->dpRedirectCollectionFactory = $dpRedirectCollectionFactory;
        $this->categoryCollectionFactory   = $categoryCollectionFactory;
        $this->customUrlLocator            = $customUrlLocator;
        $this->urlFinder                   = $urlFinder;
        $this->idEncoder                   = $idEncoder;
    }

    /**
     * @param int $storeId
     * @param string $requestPath
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getEntityUrlData(int $storeId, string $requestPath): ?array
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
            'entity_uid'    => $this->idEncoder->encode((string)$urlRewrite->getEntityId()),
            'canonical_url' => $urlRewrite->getRequestPath(),
            'relative_url'  => $urlRewrite->getRequestPath(),
            'redirect_code' => (int)$this->dpRedirectHelper->getRedirectType(),
            'redirectCode'  => (int)$this->dpRedirectHelper->getRedirectType(),
            'type'          => $this->sanitizeType($urlRewrite->getEntityType())
        ];
    }

    /**
     * @param CategoryCollection $collection
     * @param DpRedirect $redirect
     * @return CategoryInterface|DataObject
     * @throws GraphQlNoSuchEntityException
     */
    protected function getCategoryModel(CategoryCollection $collection, DpRedirect $redirect): CategoryInterface
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

        throw new GraphQlNoSuchEntityException(__("Unable to locate Category Model"));
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
