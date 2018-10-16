<?php

namespace Sparta\UrlRewriteRebuilder\Model;
use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ResourceModel\Group\CollectionFactory;
use Magento\Store\Model\ResourceModel\Group\Collection as StoreGroupCollection;
use Magento\Framework\App\ObjectManager;


class CategoryProcessor extends CategoryProcessUrlRewriteSavingObserver
{
    /**
     * @var \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator
     */
    private $categoryUrlRewriteGenerator;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer
     */
    private $urlRewriteBunchReplacer;

    /**
     * @var \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler
     */
    private $urlRewriteHandler;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool
     */
    private $databaseMapPool;

    /**
     * @var string[]
     */
    private $dataUrlRewriteClassNames;

    /**
     * @var CollectionFactory
     */
    private $storeGroupFactory;

    /**
     * @param CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator
     * @param UrlRewriteHandler $urlRewriteHandler
     * @param UrlRewriteBunchReplacer $urlRewriteBunchReplacer
     * @param DatabaseMapPool $databaseMapPool
     * @param string[] $dataUrlRewriteClassNames
     * @param CollectionFactory|null $storeGroupFactory
     */
    public function __construct(
        CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler $urlRewriteHandler,
        UrlRewriteBunchReplacer $urlRewriteBunchReplacer,
        DatabaseMapPool $databaseMapPool,
        $dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ],
        CollectionFactory $storeGroupFactory = null
    ) {
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        $this->urlRewriteHandler = $urlRewriteHandler;
        $this->urlRewriteBunchReplacer = $urlRewriteBunchReplacer;
        $this->databaseMapPool = $databaseMapPool;
        $this->dataUrlRewriteClassNames = $dataUrlRewriteClassNames;
        $this->storeGroupFactory = $storeGroupFactory
            ?: ObjectManager::getInstance()->get(CollectionFactory::class);
    }

    /**
     * Generate urls for UrlRewrite and save it in storage
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @throws \Exception
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Category $category */
        $category = $observer->getEvent()->getCategory();
        if (in_array($category->getParentId(), [Category::ROOT_CATEGORY_ID, Category::TREE_ROOT_ID])){
            return;
        }

        if (!$category->hasData('store_id')) {
            $this->setCategoryStoreId($category);
        }

        $categoryUrlRewriteResult = $this->categoryUrlRewriteGenerator->generate($category);

        try {
            $this->urlRewriteBunchReplacer->doBunchReplace($categoryUrlRewriteResult);

//            $productUrlRewriteResult = $this->urlRewriteHandler->generateProductUrlRewrites($category);
//            $this->urlRewriteBunchReplacer->doBunchReplace($productUrlRewriteResult);
        } catch (\Exception $e) {
            $message = "{$e->getMessage()} \n"
                . "Category ID = {$category->getId()} \n"
                . "URL rewrites: \n";

            $urlRewriteData = [];

            /** @var UrlRewrite $urlRewrite */
            foreach ($categoryUrlRewriteResult as $urlRewrite) {
                $urlRewriteData[] = "entity_type = {$urlRewrite->getEntityType()} \n"
                    . "entity_id = {$urlRewrite->getEntityId()} \n"
                    . "request_path = {$urlRewrite->getRequestPath()} \n"
                    . "target_path = {$urlRewrite->getTargetPath()} \n"
                    . "store_id = {$urlRewrite->getStoreId()}";
            }

            $message .= count($categoryUrlRewriteResult) . " URL rewrites: \n\n"
                . implode("\n\n ========== \n\n", $urlRewriteData);

            throw new \Exception($message);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    /**
     * in case store_id is not set for category then we can assume that it was passed through product import.
     * store group must have only one root category, so receiving category's path and checking if one of it parts
     * is the root category for store group, we can set default_store_id value from it to category.
     * it prevents urls duplication for different stores
     * ("Default Category/category/sub" and "Default Category2/category/sub")
     *
     * @param Category $category
     * @return void
     */
    private function setCategoryStoreId($category)
    {
        /** @var StoreGroupCollection $storeGroupCollection */
        $storeGroupCollection = $this->storeGroupFactory->create();

        foreach ($storeGroupCollection as $storeGroup) {
            /** @var \Magento\Store\Model\Group $storeGroup */
            if (in_array($storeGroup->getRootCategoryId(), explode('/', $category->getPath()))) {
                $category->setStoreId($storeGroup->getDefaultStoreId());
            }
        }
    }
}
