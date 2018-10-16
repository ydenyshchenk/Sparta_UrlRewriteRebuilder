<?php

namespace Sparta\UrlRewriteRebuilder\Model;
use Magento\Catalog\Model\Product;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Observer\ProductProcessUrlRewriteSavingObserver;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\Framework\Event\ObserverInterface;

class ProductProcessor extends ProductProcessUrlRewriteSavingObserver
{
    /**
     * @var ProductUrlRewriteGenerator
     */
    private $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    private $urlPersist;

    /**
     * @param ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param UrlPersistInterface $urlPersist
     */
    public function __construct(
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersist
    ) {
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();

        if ($product->dataHasChangedFor('url_key')
            || $product->getIsChangedCategories()
            || $product->getIsChangedWebsites()
            || $product->dataHasChangedFor('visibility')
        ) {
            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $product->getId(),
                UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::REDIRECT_TYPE => 0,
                UrlRewrite::STORE_ID => $product->getStoreId()
            ]);

            if ($product->isVisibleInSiteVisibility()) {
                $urlRewrites = $this->productUrlRewriteGenerator->generate($product);

                try {
                    $this->urlPersist->replace($urlRewrites);
                } catch (\Exception $e) {
                    $message = "{$e->getMessage()} \n"
                        . "Product ID = {$product->getId()} \n"
                        . "URL rewrites: \n";

                    $urlRewriteData = [];

                    /** @var UrlRewrite $urlRewrite */
                    foreach ($urlRewrites as $urlRewrite) {
                        $urlRewriteData[] = "entity_type = {$urlRewrite->getEntityType()} \n"
                            . "entity_id = {$urlRewrite->getEntityId()} \n"
                            . "request_path = {$urlRewrite->getRequestPath()} \n"
                            . "target_path = {$urlRewrite->getTargetPath()} \n"
                            . "store_id = {$urlRewrite->getStoreId()}";
                    }

                    $message .= count($urlRewrites) . " URL rewrites: \n\n"
                        . implode("\n\n ========== \n\n", $urlRewriteData);

                    throw new \Exception($message);
                    return \Magento\Framework\Console\Cli::RETURN_FAILURE;
                }
            }
        }
    }
}