<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\SeoRedirectsGraphQl\Model;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderComposite;
use MageWorx\SeoRedirects\Api\Data\CustomRedirectInterface;
use MageWorx\SeoRedirects\Helper\CustomRedirect\Data as CustomRedirectHelper;
use MageWorx\SeoRedirects\Model\Redirect\CustomRedirect;
use MageWorx\SeoRedirects\Model\Redirect\Source\CustomRedirect\RedirectTypeRewriteFragment
    as RedirectTypeRewriteFragmentSource;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\Collection as CustomRedirectCollection;
use MageWorx\SeoRedirects\Model\ResourceModel\Redirect\CustomRedirect\CollectionFactory
    as CustomRedirectCollectionFactory;
use MageWorx\SeoRedirectsGraphQl\Model\Source\CustomRedirectType as CustomRedirectTypeOptions;
use Magento\Framework\GraphQl\Query\Uid;

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
     * @var Uid
     */
    protected $idEncoder;

    /**
     * @var EntityDataProviderComposite
     */
    protected $entityDataProviderComposite;

    /**
     * @param CustomRedirectHelper $customRedirectHelper
     * @param CustomRedirectCollectionFactory $customRedirectCollectionFactory
     * @param CustomRedirectTypeOptions $customRedirectTypeOptions
     * @param RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource
     * @param UrlFinderInterface $urlFinder
     * @param Uid $idEncoder
     * @param EntityDataProviderComposite $entityDataProviderComposite
     */
    public function __construct(
        CustomRedirectHelper $customRedirectHelper,
        CustomRedirectCollectionFactory $customRedirectCollectionFactory,
        CustomRedirectTypeOptions $customRedirectTypeOptions,
        RedirectTypeRewriteFragmentSource $redirectTypeRewriteFragmentSource,
        UrlFinderInterface $urlFinder,
        Uid $idEncoder,
        EntityDataProviderComposite $entityDataProviderComposite
    ) {
        $this->customRedirectHelper              = $customRedirectHelper;
        $this->customRedirectCollectionFactory   = $customRedirectCollectionFactory;
        $this->customRedirectTypeOptions         = $customRedirectTypeOptions;
        $this->redirectTypeRewriteFragmentSource = $redirectTypeRewriteFragmentSource;
        $this->urlFinder                         = $urlFinder;
        $this->idEncoder                         = $idEncoder;
        $this->entityDataProviderComposite       = $entityDataProviderComposite;
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

        $customRedirect = $this->getCustomRedirect($storeId, $objectId, $objectType);

        if (!$customRedirect || !$customRedirect->getId()) {
            return null;
        }

        $urlRewrite = $this->getUrlRewrite($customRedirect);

        if (!$urlRewrite) {
            return null;
        }

        return [
            'id'            => $urlRewrite->getEntityId(),
            'entity_uid'    => $this->idEncoder->encode((string)$urlRewrite->getEntityId()),
            'canonical_url' => $urlRewrite->getRequestPath(),
            'relative_url'  => $urlRewrite->getRequestPath(),
            'redirectCode'  => (int)$customRedirect->getRedirectCode(),
            'redirect_code' => (int)$customRedirect->getRedirectCode(),
            'type'          => $this->sanitizeType($urlRewrite->getEntityType())
        ];
    }

    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @param ResolveInfo $info
     * @return array|null
     */
    public function getRouteData(int $storeId, int $objectId, string $objectType, ResolveInfo $info): ?array
    {
        if (!$this->customRedirectHelper->isEnabled($storeId)) {
            return null;
        }

        $customRedirect = $this->getCustomRedirect($storeId, $objectId, $objectType);

        if (!$customRedirect || !$customRedirect->getId()) {
            return null;
        }

        $urlRewrite = $this->getUrlRewrite($customRedirect);

        if (!$urlRewrite) {
            return null;
        }

        $type   = $this->sanitizeType($urlRewrite->getEntityType());
        $result = $this->entityDataProviderComposite->getData($type, (int)$urlRewrite->getEntityId(), $info, $storeId);

        $result['redirect_code'] = (int)$customRedirect->getRedirectCode();
        $result['relative_url']  = $urlRewrite->getRequestPath();
        $result['type']          = $type;

        return $result;
    }

    /**
     * @param int $storeId
     * @param int $objectId
     * @param string $objectType
     * @return CustomRedirect|null
     */
    protected function getCustomRedirect(int $storeId, int $objectId, string $objectType): ?CustomRedirect
    {
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

        return $customRedirectCollection->getFirstItem();
    }

    /**
     * @param CustomRedirect $customRedirect
     * @return UrlRewrite|null
     */
    protected function getUrlRewrite(CustomRedirect $customRedirect): ?UrlRewrite
    {
        $redirectTypeRewriteFragmentSource = $this->redirectTypeRewriteFragmentSource->toArray();

        if (empty($redirectTypeRewriteFragmentSource[$customRedirect->getTargetEntityType()])) {
            return null;
        }

        $targetPath = $redirectTypeRewriteFragmentSource[$customRedirect->getTargetEntityType()]
            . $customRedirect->getTargetEntityIdentifier();

        return $this->getRewriteByTargetPath($targetPath, (int)$customRedirect->getStoreId());
    }

    /**
     * @param string $targetPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    protected function getRewriteByTargetPath(string $targetPath, int $storeId): ?UrlRewrite
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
