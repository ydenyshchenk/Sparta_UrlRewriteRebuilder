<?php

namespace Sparta\UrlRewriteRebuilder\Model;
use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;

use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;


class CategoryProcessor extends CategoryProcessUrlRewriteSavingObserver
{
    /**
     * Category UrlRewrite generator.
     *
     * @var CategoryUrlRewriteGenerator
     */
    private $categoryUrlRewriteGenerator;

    /**
     * @param \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator
     * @param UrlRewriteHandler $urlRewriteHandler
     * @param UrlRewriteBunchReplacer $urlRewriteBunchReplacer
     * @param DatabaseMapPool $databaseMapPool
     * @param string[] $dataUrlRewriteClassNames
     */
    public function __construct(
        \Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        \Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler $urlRewriteHandler,
        \Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer $urlRewriteBunchReplacer,
        \Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool $databaseMapPool,
        $dataUrlRewriteClassNames = [
            \Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap::class,
            \Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap::class
        ]
    ) {
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        parent::__construct(
            $categoryUrlRewriteGenerator,
            $urlRewriteHandler,
            $urlRewriteBunchReplacer,
            $databaseMapPool,
            $dataUrlRewriteClassNames
        );
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
        /** @var Registry $registry */
        $registry = ObjectManager::getInstance()->get(Registry::class);
        $isInternal = $registry->registry('Sparta_UrlRewriteRebuilder');
        if (empty($isInternal)) {
            return parent::execute($observer);
        }

        /** @var Category $category */
        $category = $observer->getEvent()->getCategory();
        if (in_array($category->getParentId(), [Category::ROOT_CATEGORY_ID, Category::TREE_ROOT_ID])){
            return;
        }

        $urlRewrites = $this->categoryUrlRewriteGenerator->generate($category);

        try {
            $this->urlPersist->replace($urlRewrites);
        } catch (\Exception $e) {
            $message = "{$e->getMessage()} \n"
                . "Category ID = {$category->getId()} \n";

            throw new \Exception($message);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}
