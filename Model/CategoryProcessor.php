<?php

namespace Sparta\UrlRewriteRebuilder\Model;
use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver;

class CategoryProcessor extends CategoryProcessUrlRewriteSavingObserver
{
    /**
     * Generate urls for UrlRewrite and save it in storage
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Category $category */
        $category = $observer->getEvent()->getCategory();
        if (in_array($category->getParentId(), [Category::ROOT_CATEGORY_ID, Category::TREE_ROOT_ID])){
            return;
        }

        $urlRewrites = $this->categoryUrlRewriteGenerator->generate($category);
        $this->urlPersist->replace($urlRewrites);
    }
}
