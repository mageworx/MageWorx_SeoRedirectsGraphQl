<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use MageWorx\SeoRedirects\Api\Data\CustomRedirectInterface;
use MageWorx\SeoRedirects\Helper\CustomRedirect\Data as CustomRedirectHelper;
use MageWorx\SeoRedirects\Model\Redirect\CustomRedirect;
use MageWorx\SeoRedirects\Model\Redirect\Source\CustomRedirect\RedirectTypeRewriteFragment
    as RedirectTypeRewriteFragmentSource;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\Collection as CustomRedirectCollection;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\CollectionFactory
    as CustomRedirectCollectionFactory;
use MageWorx\SeoRedirectsGraphQl\Model\Source\CustomRedirectType as CustomRedirectTypeOptions;

class CustomRedirectDataProvider implements CustomRedirectDataProviderInterface
{
    /**
     * @var CustomRedirectHelper
     */
    protected $customRedirectHelper;

    /**
     * @var CustomRedirectCollectionFactory
     */
    protected $customRedirectCollectionFactory;

    /**
     * @var CustomRedirectTypeOptions
     */
    protected $customRedirectTypeOptions;

    /**
     * @var UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var RedirectTypeRewriteFragmentSource
     */
    protected $redirectTypeRewriteFragmentSource;

    /**
     * CustomRedirectDataProvider constructor.
     *
     * @param CustomRedirectHelper $customRedirectHelper
     * @param CustomRedirectCollectionFactory $customRedirectCollectionFactory
     * @param CustomRedirectTypeOptions $customRedirectTypeOptions
     * @param RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource
     * @param UrlFinderInterface $urlFinder
     */
    public function __construct(
        CustomRedirectHelper $customRedirectHelper,
        CustomRedirectCollectionFactory $customRedirectCollectionFactory,
        CustomRedirectTypeOptions $customRedirectTypeOptions,
        RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource,
        UrlFinderInterface $urlFinder
    ) {
        $this->customRedirectHelper              = $customRedirectHelper;
        $this->customRedirectCollectionFactory   = $customRedirectCollectionFactory;
        $this->customRedirectTypeOptions         = $customRedirectTypeOptions;
        $this->redirectTypeRewriteFragmentSource = $redirectTypeRewriteFragmentSource;
        $this->urlFinder                         = $urlFinder;
    }

    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @return array|null
     */
    public function getEntityUrlData(int $storeId, int $objectId, string $objectType): ?array
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
