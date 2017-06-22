<?php

namespace Sparta\UrlRewriteRebuilder\Console\Command;
use Magento\Framework\DB\Select;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Backend\App\Area\FrontNameResolver;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Catalog\Model\Category;

class Rebuilder extends Command
{
    /**
     * Error collector
     *
     * @var array
     */
    protected $errors = [];

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $progressBar;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Magento\Framework\DB\Adapter\Pdo\Mysql
     */
    protected $connection;

    /**
     * @var \Magento\Framework\Module\ModuleResource
     */
    protected $moduleResource;

    /**
     * Contain name of ID column: entity_id or row_id
     *
     * @var $idColumn
     */
    protected $idColumn = 'entity_id';

    /**
     * Category attribute_id for "url_key"
     *
     * @var null|int
     */
    protected $categoryAttributeUrlKeyId = NULL;

    /**
     * Category attribute_id for "url_path"
     *
     * @var null|int
     */
    protected $categoryAttributeUrlPathId = NULL;

    /**
     * Product attribute_id for "url_key"
     *
     * @var null|int
     */
    protected $productAttributeUrlKeyId = NULL;

    /**
     * Product attribute_id for "url_path"
     *
     * @var null|int
     */
    protected $productAttributeUrlPathId = NULL;

    /**
     * Product attribute_id for "visibility"
     *
     * @var null|int
     */
    protected $productAttributeVisibilityId = NULL;

    /**
     * Batch size limitation
     */
    const BATCH_SIZE = 100;

    const LOG_FILE = './var/log/sparta_urlRewriteRebuilder.log';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sparta:rebuild:rewrites');
        $this->setDescription('Rebuild Product URL Rewrites');
        parent::configure();
    }

    /**
     * Returns formatted time
     *
     * @param $time
     * @return string
     */
    protected function formatTime($time)
    {
        return sprintf('%02d:%02d:%02d', ($time / 3600), ($time / 60 % 60), $time % 60);
    }

    /**
     * Returns abstract resource
     *
     * @return \Magento\Framework\Module\ModuleResource|mixed
     */
    protected function getModuleResource()
    {
        if ($this->moduleResource == null) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $this->moduleResource = $this->getObjectManager()->get('Magento\Framework\Module\ModuleResource');
        }
        return $this->moduleResource;
    }

    /**
     * Returns connection
     *
     * @return false|\Magento\Framework\DB\Adapter\AdapterInterface|\Magento\Framework\DB\Adapter\Pdo\Mysql
     */
    protected function getConnection()
    {
        if ($this->connection == null) {
            $this->connection = $this->getModuleResource()->getConnection();
        }

        return $this->connection;
    }

    /**
     * Setup progress bar
     *
     * @return $this
     */
    private function setupProgress()
    {
        $this->progressBar = new \Symfony\Component\Console\Helper\ProgressBar($this->output);
        $this->progressBar->setFormat('<info>%message%</info> %current%/%max% [%bar%] %percent:3s%%');
        return $this;
    }

    /**
     * Gets initialized object manager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    protected function getObjectManager()
    {
        if (null == $this->objectManager) {
            $area = FrontNameResolver::AREA_CODE;
            $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\Framework\App\State $appState */
            $appState = $this->objectManager->get('Magento\Framework\App\State');
            $appState->setAreaCode($area);
            $configLoader = $this->objectManager->get('Magento\Framework\ObjectManager\ConfigLoaderInterface');
            $this->objectManager->configure($configLoader->load($area));
        }
        return $this->objectManager;
    }

    /**
     * Retrieves category attribute_id for "url_key"
     *
     * @return int|null
     */
    protected function getCategoryAttributeUrlKeyId()
    {
        if ($this->categoryAttributeUrlKeyId === NULL) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $moduleResource = $this->getModuleResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->getConnection();

            /** @var \Magento\Framework\DB\Select $select */
            $select = $connection->select();
            $select->from(array('eav' => $moduleResource->getTable('eav_attribute')), 'eav.attribute_id')
                ->join(
                    array('eavt' => $moduleResource->getTable('eav_entity_type')),
                    'eav.entity_type_id = eavt.entity_type_id')
                ->where('eav.attribute_code = ?', 'url_key')
                ->where('eavt.entity_type_code = ?', 'catalog_category');

            $this->categoryAttributeUrlKeyId = (int)$connection->query($select)->fetchColumn();
        }

        return $this->categoryAttributeUrlKeyId;
    }

    /**
     * Retrieves category attribute_id for "url_path"
     *
     * @return int|null
     */
    protected function getCategoryAttributeUrlPathId()
    {
        if ($this->categoryAttributeUrlPathId === NULL) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $moduleResource = $this->getModuleResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->getConnection();

            /** @var \Magento\Framework\DB\Select $select */
            $select = $connection->select();
            $select->from(array('eav' => $moduleResource->getTable('eav_attribute')), 'eav.attribute_id')
                ->join(
                    array('eavt' => $moduleResource->getTable('eav_entity_type')),
                    'eav.entity_type_id = eavt.entity_type_id')
                ->where('eav.attribute_code = ?', 'url_path')
                ->where('eavt.entity_type_code = ?', 'catalog_category');

            $this->categoryAttributeUrlPathId = (int)$connection->query($select)->fetchColumn();
        }

        return $this->categoryAttributeUrlPathId;
    }

    /**
     * Retrieves product attribute_id for "url_key"
     *
     * @return int|null
     */
    protected function getProductAttributeUrlKeyId()
    {
        if ($this->productAttributeUrlKeyId === NULL) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $moduleResource = $this->getModuleResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->getConnection();

            /** @var \Magento\Framework\DB\Select $select */
            $select = $connection->select();
            $select->from(array('eav' => $moduleResource->getTable('eav_attribute')), 'eav.attribute_id')
                ->join(
                    array('eavt' => $moduleResource->getTable('eav_entity_type')),
                    'eav.entity_type_id = eavt.entity_type_id'
                )->where('eav.attribute_code = ?', 'url_key')
                ->where('eavt.entity_type_code = ?', 'catalog_product');
            $this->productAttributeUrlKeyId = (int)$connection->query($select)->fetchColumn();
        }

        return $this->productAttributeUrlKeyId;
    }

    /**
     * Retrieves product attribute_id for "url_path"
     *
     * @return int|null
     */
    protected function getProductAttributeUrlPathId()
    {
        if ($this->productAttributeUrlPathId == NULL) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $moduleResource = $this->getModuleResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->getConnection();

            /** @var \Magento\Framework\DB\Select $select */
            $select = $connection->select();
            $select->from(array('eav' => $moduleResource->getTable('eav_attribute')), 'eav.attribute_id')
                ->join(
                    array('eavt' => $moduleResource->getTable('eav_entity_type')),
                    'eav.entity_type_id = eavt.entity_type_id'
                )->where('eav.attribute_code = ?', 'url_path')
                ->where('eavt.entity_type_code = ?', 'catalog_product');
            $this->productAttributeUrlPathId = (int)$connection->query($select)->fetchColumn();
        }
        return $this->productAttributeUrlPathId;
    }

    /**
     * Retrieves product attribute_id for "visibility"
     *
     * @return int|null
     */
    protected function getProductAttributeVisibilityId()
    {
        if ($this->productAttributeVisibilityId == NULL) {
            /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
            $moduleResource = $this->getModuleResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $this->getConnection();

            /** @var \Magento\Framework\DB\Select $select */
            $select = $connection->select();
            $select->from(array('eav' => $moduleResource->getTable('eav_attribute')), 'eav.attribute_id')
                ->join(
                    array('eavt' => $moduleResource->getTable('eav_entity_type')),
                    'eav.entity_type_id = eavt.entity_type_id'
                )->where('eav.attribute_code = ?', 'visibility')
                ->where('eavt.entity_type_code = ?', 'catalog_product');
            $this->productAttributeVisibilityId = (int)$connection->query($select)->fetchColumn();
        }
        return $this->productAttributeVisibilityId;
    }

    /**
     * Initialization
     *
     * @return $this
     */
    protected function init()
    {
        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();

        $description = $connection->describeTable($moduleResource->getTable('catalog_product_entity'));
        if (isset($description['row_id'])) {
            $this->idColumn = 'row_id';
        }
        return $this;
    }

    /**
     * Truncates URL rewrite indexer tables
     *
     * @return $this
     */
    protected function truncateTables()
    {
        $this->output->write('[STEP 1] Truncating tables `catalog_url_rewrite_product_category` and `url_rewrite`... ');

        $microTimeStart = microtime(true);

        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();
        $connection->query('set foreign_key_checks = 0');
        $connection->truncateTable($moduleResource->getTable('catalog_url_rewrite_product_category'));
        $connection->truncateTable($moduleResource->getTable('url_rewrite'));
        $connection->query('set foreign_key_checks = 1');

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('performed successfully in ' . $this->formatTime($microTimeDiff), true);
        return $this;
    }

    /**
     * Rebuilding URL rewrites for CMS pages
     *
     * @return $this
     */
    protected function rebuildCmsUrlRewrites()
    {
        $this->output->write('[STEP 2] Rebuilding URL rewrites for CMS pages... ');
        $microTimeStart = microtime(true);

        /** @var \Magento\Cms\Model\ResourceModel\Page\Collection $cmsPageCollection */
        $cmsPageCollection = $this->getObjectManager()->get('Magento\Cms\Model\ResourceModel\Page\Collection');

        /** @var |Magento\Framework\DB\Select $select */
        $select = $cmsPageCollection->getSelect();

        $select->columns($cmsPageCollection->getResource()->getIdFieldName(), 'main_table')
            ->limit(self::BATCH_SIZE);

        $offset = 0;
        $counter = 0;
        while ($cmsPageIds = $this->getConnection()->fetchCol($select)) {
            /** @var \Magento\Cms\Model\Page $cmsPage */
            $cmsPage = $this->getObjectManager()->get('Magento\Cms\Model\Page');
            $eventName = 'cms_page_save_after';

            foreach ($cmsPageIds as $cmsPageId) {
                $cmsPageId = (int)$cmsPageId;
                $cmsPage->load($cmsPageId);
                $cmsPage->setOrigData('identifier', '');

                $data = ['object' => $cmsPage];

                /** @var \Magento\Framework\Event $event */
                $event = new Event($data);
                $event->setName($eventName);

                /** @var \Magento\Framework\Event\Observer $observer */
                $observer = new Observer();
                $observer->setData(array_merge(['event' => $event], $data));

                /** @var \Magento\CmsUrlRewrite\Observer\ProcessUrlRewriteSavingObserver $cmsPageProcess */
                $cmsPageProcess = $this->getObjectManager()
                    ->get('Magento\CmsUrlRewrite\Observer\ProcessUrlRewriteSavingObserver');
                $cmsPageProcess->execute($observer);

                $counter++;
            }

            $offset += self::BATCH_SIZE;
            $select->limit(self::BATCH_SIZE, $offset);
        }

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('performed successfully in ' . $this->formatTime($microTimeDiff), true);
        return $this;
    }

    /**
     * Checks and resolves duplicate URL keys for categories
     *
     * @return $this
     */
    protected function checkCategoryUrlKeys()
    {
        $this->output->write('[STEP 3] Checking for duplicate url_key category values', true);
        $microTimeStart = microtime(true);

        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();

        $duplicateCategoryUrlKeysTable = $moduleResource->getTable('sparta_duplicate_category_url_keys');
        $categoryVarCharTable = $moduleResource->getTable('catalog_category_entity_varchar');

        if ($connection->isTableExists($duplicateCategoryUrlKeysTable)) {
            $connection->dropTable($duplicateCategoryUrlKeysTable);
        }
        $table = $connection->newTable($duplicateCategoryUrlKeysTable)
            ->addColumn(
                'store_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Store ID'
            )->addColumn(
                'url_key',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'URL Key'
            )->addColumn(
                'url_path',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'URL Key'
            )->addColumn(
                'count',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Count'
            )->addColumn(
                'parent_path',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'Parent Path'
            )->addColumn(
                'ids',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'Concatenated Entity/Row IDs'
            );
        $connection->createTable($table);

        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select();
        $select->from(
            array('cuk' => $categoryVarCharTable),
            array(
                'store_id',
                'url_key' => 'value',
                'count' => "COUNT(`cuk`.`{$this->idColumn}`)",
                'parent_path' => 'REPLACE(`cce`.`path`, `cce`.`entity_id`, "")',
                'ids' => "GROUP_CONCAT(`cuk`.{$this->idColumn})"
            )
        )->join(
            array('cce' => $moduleResource->getTable('catalog_category_entity')),
            "cce.{$this->idColumn} = cuk.{$this->idColumn}",
            ''
        )->join(
            array('cup' => $categoryVarCharTable),
            "cce.{$this->idColumn} = cup.{$this->idColumn} AND cup.store_id = cuk.store_id "
                . " AND cup.attribute_id = {$this->getCategoryAttributeUrlPathId()}",
            array('url_path' => 'cup.value')
        )->where('cuk.attribute_id = ?', $this->getCategoryAttributeUrlKeyId())
            ->group(array('url_key', 'parent_path', 'store_id'))
            ->having('count > 1');

        $query = $connection->insertFromSelect($select, $duplicateCategoryUrlKeysTable);
        $connection->query($query);
        $select->reset();

        $select->from(array('sdcuk' => $duplicateCategoryUrlKeysTable), 'COUNT(*)');
        $duplicateCount = (int)$connection->query($select)->fetchColumn();
        $select->reset();

        $this->output->write("         Found $duplicateCount duplicate url keys", true);

        if ($duplicateCount) {
            $offset = 0;

            $select->from(array('sdcuk' => $duplicateCategoryUrlKeysTable), '*')->limit(self::BATCH_SIZE, $offset);
            $duplicates = $connection->query($select)->fetchAll();
            $subSelect = $connection->select()
                ->from(array('ccev' => $categoryVarCharTable), ['value_id', $this->idColumn]);

            $this->progressBar->setMessage('         Adding "-[0-9]" suffixes to duplicate url_keys');
            $this->progressBar->start($duplicateCount);

            while (count($duplicates) > 0) {
                foreach ($duplicates as $data) {
                    $subSelect->reset(\Magento\Framework\DB\Select::WHERE);
                    $subSelect->where('value = ?', $data['url_key'])
                        ->where('attribute_id = ?', $this->getCategoryAttributeUrlKeyId())
                        ->where('store_id = ?', (int)$data['store_id'])
                        ->where($this->idColumn . ' IN (?)', explode(',', $data['ids']))
                        ->order('value_id');

                    $valueIds = $connection->query($subSelect)->fetchAll();
                    array_shift($valueIds);
                    $suffixId = 1;
                    foreach ($valueIds as $valueIdRow) {
                        $urlKey = $data['url_key'] . '-' . $suffixId;
                        $connection->update($categoryVarCharTable,
                            array('value' => $urlKey),
                            array('value_id = ?' => (int)$valueIdRow['value_id'])
                        );

                        $urlPath = $data['url_path'] . '-' . $suffixId;
                        $connection->update($categoryVarCharTable,
                            array('value' => $urlPath),
                            array(
                                $this->idColumn . ' = ?' => $valueIdRow[$this->idColumn],
                                'attribute_id = ?' => $this->getCategoryAttributeUrlPathId()
                            )
                        );
                        $suffixId++;
                    }
                    $this->progressBar->advance();
                }

                $offset += self::BATCH_SIZE;
                $select->limit(self::BATCH_SIZE, $offset);
                $duplicates = $connection->query($select)->fetchAll();
            }

            $this->progressBar->finish();
            $this->output->write('', true);
        }

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('         ' . $duplicateCount . ' duplicates of url_key category values were processed in '
            . $this->formatTime($microTimeDiff), true);

        return $this;
    }

    /**
     * Checks and resolves duplicates of URL keys for products
     *
     * @return $this
     */
    protected function checkProductUrlKeys()
    {
        $this->output->write('[STEP 4] Checking for duplicate url_key product values', true);
        $microTimeStart = microtime(true);

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();

        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();

        $duplicateProductUrlKeysTable = $moduleResource->getTable('sparta_duplicate_product_url_keys');
        $productVarCharTable = $moduleResource->getTable('catalog_product_entity_varchar');

        if ($connection->isTableExists($duplicateProductUrlKeysTable)) {
            $connection->dropTable($duplicateProductUrlKeysTable);
        }
        $table = $connection->newTable($duplicateProductUrlKeysTable)
            ->addColumn(
                'url_key',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'URL Key'
            )->addColumn(
                'count',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Count'
            );

        $connection->createTable($table);

        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select();

        $this->productAttributeUrlKeyId = $this->getProductAttributeUrlKeyId();

        $select->from(
            array('cpev' => $productVarCharTable),
            array('url_key' => 'value', 'count' => "COUNT({$this->idColumn})")
        )->where('cpev.attribute_id = ?', $this->getProductAttributeUrlKeyId())
            ->group(array('value'))
            ->having('count > 1');

        $query = $connection->insertFromSelect($select, $duplicateProductUrlKeysTable);
        $connection->query($query);
        $select->reset();

        $select->from(array('sdpuk' => $duplicateProductUrlKeysTable), 'COUNT(*)');
        $duplicateCount = (int)$connection->query($select)->fetchColumn();
        $select->reset();

        $this->output->write("         Found $duplicateCount duplicate url keys", true);

        if ($duplicateCount) {
            $offset = 0;

            $select->from(array('sdpuk' => $duplicateProductUrlKeysTable), '*')->limit(self::BATCH_SIZE, $offset);
            $duplicates = $connection->query($select)->fetchAll();
            $subSelect = $connection->select()->from(array('cpev' => $productVarCharTable), 'value_id');

            $this->progressBar->setMessage('         Adding "-[0-9]" suffixes to duplicate url_keys');
            $this->progressBar->start($duplicateCount);

            while (count($duplicates) > 0) {
                foreach ($duplicates as $data) {
                    $subSelect->reset(\Magento\Framework\DB\Select::WHERE);
                    $subSelect->where('value = ?', $data['url_key'])
                        ->where('attribute_id = ?', $this->getProductAttributeUrlKeyId())
                        ->order('value_id');

                    $valueIds = $connection->query($subSelect)->fetchAll();
                    array_shift($valueIds);
                    $suffixId = 1;
                    foreach ($valueIds as $valueIdRow) {
                        $connection->update($productVarCharTable,
                            array('value' => $data['url_key'] . '-' . $suffixId),
                            array('value_id = ?' => (int)$valueIdRow['value_id'])
                        );
                        $suffixId++;
                    }
                    $this->progressBar->advance();

                }
                $offset += self::BATCH_SIZE;
                $select->limit(self::BATCH_SIZE, $offset);
                $duplicates = $connection->query($select)->fetchAll();
            }

            $this->progressBar->finish();
            $this->output->write('', true);
        }

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('         ' . $duplicateCount . ' duplicates of url_key product values were processed in '
            . $this->formatTime($microTimeDiff), true);

        return $this;
    }

    /**
     * Rebuilding URL rewrites for categories
     *
     * @return $this
     */
    protected function rebuildCategoryUrlRewrites()
    {
        $this->output->write('[STEP 5] Rebuilding URL rewrites for categories... ', true);
        $microTimeStart = microtime(true);

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();

        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();

        /** @var \Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver $categoryProcess */
        $categoryProcess = $this->getObjectManager()
            ->get('Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver');

        $counter = 0;
        $categoryId = 0;

        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select();

        $categoryEntityTable = $moduleResource->getTable('catalog_category_entity');
        $categoryVarCharTable = $moduleResource->getTable('catalog_category_entity_varchar');

        $select->from(['vuk' => $categoryVarCharTable], ['vuk.value as url_key', 'vuk.store_id'])
            ->join(
                ['e' => $categoryEntityTable],
                "e.{$this->idColumn} = vuk.{$this->idColumn}"
            )
            ->join(
                ['vup' => $categoryVarCharTable],
                "e.{$this->idColumn} = vup.{$this->idColumn} AND vup.store_id = vuk.store_id"
                . " AND vup.attribute_id = {$this->getCategoryAttributeUrlPathId()}",
                'vup.value as url_path'
            )
            ->limit(self::BATCH_SIZE)
            ->where("vuk.attribute_id = ?", $this->getCategoryAttributeUrlKeyId())
            ->where("e.{$this->idColumn} > ?", $categoryId);

        $countSelect = clone $select;
        $countSelect->reset(Select::COLUMNS)->reset(Select::LIMIT_COUNT)->columns('COUNT(*)');
        $duplicateCount = (int)$connection->query($countSelect)->fetchColumn();

        if ($duplicateCount) {
            $this->progressBar->setMessage('         Rebuilding URL rewrites for categories');
            $this->progressBar->start($duplicateCount);

            $categories = $connection->fetchAll($select);

            /** @var \Magento\Catalog\Model\Category $categoryModel */
            $categoryModel = $this->getObjectManager()->get('Magento\Catalog\Model\Category');

            while (count($categories) > 0) {
                $eventName = 'catalog_category_save_after';

                foreach ($categories as $category) {
                    $categoryId = (int)$category[$this->idColumn];

                    if (in_array($category['parent_id'], [Category::ROOT_CATEGORY_ID, Category::TREE_ROOT_ID])){
                        $this->progressBar->advance();
                        $counter++;
                        continue;
                    }

                    $categoryModel->unsetData();
                    $categoryModel->setData($category);

                    $data = ['category' => $categoryModel];

                    /** @var \Magento\Framework\Event $event */
                    $event = new Event($data);
                    $event->setName($eventName);

                    /** @var \Magento\Framework\Event\Observer $observer */
                    $observer = new Observer();
                    $observer->setData(array_merge(['event' => $event], $data));

                    try {
                        $categoryProcess->execute($observer);
                    } catch (\Exception $e) {
                        $message = "\n[ERROR] {$e->getMessage()} \ncategoryID = $categoryId";
                        $this->output->writeln($message);
                        $this->errors['Category ID = ' . $category['entity_id']] = $message;
                    }
                    $this->progressBar->advance();
                    $counter++;
                }

                $select->reset(Select::WHERE)->where("vuk.attribute_id = ?", $this->getCategoryAttributeUrlKeyId())
                    ->where("e.{$this->idColumn} > ?", $categoryId);
                $categories = $connection->fetchAll($select);
            }

            $this->progressBar->finish();
            $this->output->write('', true);
        }

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('         Successfully regenerated URL rewrites for ' . $duplicateCount
            . ' categories and linked products in ' . $this->formatTime($microTimeDiff), true);

        return $this;
    }

    /**
     * Rebuilding URL rewrites for products
     *
     * @return $this
     */
    protected function rebuildProductUrlRewrites()
    {
        $this->output->write('[STEP 6] Rebuilding URL rewrites for products... ', true);
        $microTimeStart = microtime(true);

        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->getConnection();

        /** @var \Magento\Framework\Module\ModuleResource $moduleResource */
        $moduleResource = $this->getModuleResource();

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->getObjectManager()->get('Sparta\UrlRewriteRebuilder\Model\Proxy\Product');

        /** @var \Magento\Catalog\Model\Product $productModel */
        $productModel = $this->getObjectManager()->get('Magento\Catalog\Model\Product');

        /** @var \Magento\CatalogUrlRewrite\Observer\ProductProcessUrlRewriteSavingObserver $productProcess */
        $productProcess = $this->getObjectManager()->get('Magento\CatalogUrlRewrite\Observer\ProductProcessUrlRewriteSavingObserver');

        /** @var \Magento\Framework\Event\Observer $observer */
        $observer = new Observer();

        /** @var \Magento\Framework\Event $event */
        $event = new Event();

        $productEntityTable = $moduleResource->getTable('catalog_product_entity');
        $productIntTable = $moduleResource->getTable('catalog_product_entity_int');
        $productVarCharTable = $moduleResource->getTable('catalog_product_entity_varchar');

        $counter = 0;
        $productId = 0;

        /** @var \Magento\Framework\DB\Select $select */
        $select = $connection->select();
        $select->from(['vuk' => $productVarCharTable], ['vuk.value as url_key', 'vuk.store_id'])
            ->join(
                ['e' => $productEntityTable],
                "e.{$this->idColumn} = vuk.{$this->idColumn}"
            )
            ->join(
                ['vup' => $productVarCharTable],
                "vuk.{$this->idColumn} = vup.{$this->idColumn} AND vup.store_id = vuk.store_id"
                    . " AND vup.attribute_id = {$this->getProductAttributeUrlPathId()}",
                'vup.value as url_path'
            )
            ->join(
                ['dv' => $productIntTable],
                "vuk.{$this->idColumn} = dv.{$this->idColumn} AND dv.store_id = 0"
                    . " AND dv.attribute_id = {$this->getProductAttributeVisibilityId()}",
                ''
            )
            ->joinLeft(
                ['sv' => $productIntTable],
                "vuk.{$this->idColumn} = sv.{$this->idColumn} AND sv.store_id = vuk.store_id"
                    . " AND sv.attribute_id = {$this->getProductAttributeVisibilityId()}",
                ''
            )
            ->columns(['visibility' => 'IFNULL(sv.value, dv.value)'])
            ->limit(self::BATCH_SIZE)
            ->where('IFNULL(sv.value, dv.value) IN (?)', $productModel->getVisibleInSiteVisibilities())
            ->where("vuk.attribute_id = ?", $this->getProductAttributeUrlKeyId())
            ->where("vuk.{$this->idColumn} > ?", $productId);

        $countSelect = clone $select;
        $countSelect->reset(Select::COLUMNS)->columns('COUNT(*)');
        $duplicateCount = (int)$connection->query($countSelect)->fetchColumn();

        if ($duplicateCount) {
            $this->progressBar->setMessage('         Rebuilding URL rewrites for products');
            $this->progressBar->start($duplicateCount);

            $products = $connection->fetchAll($select);

            while (count($products) > 0) {
                $eventName = 'catalog_product_save_after';

                foreach ($products as $product) {
                    $productId = (int)$product[$this->idColumn];
                    $productModel->setData($product);
                    $productModel->setOrigData('url_key', '');
                    $productModel->setUrlPath(null);
                    $data = ['product' => $productModel];

                    $event->addData($data);
                    $event->setName($eventName);

                    $observer->setData(array_merge(['event' => $event], $data));

                    try {
                        $productProcess->execute($observer);
                    } catch (\Exception $e) {
                        $message = "\n[ERROR] " . $e->getMessage() . "\nproductID = {$product['entity_id']}";
                        $this->output->writeln($message);
                        $this->errors['Product ID = ' . $product['entity_id']] = $message;
                    }
                    $this->progressBar->advance();
                    $counter++;
                    $productModel->unsetData();
                    $event->unsetData();
                    $observer->unsetData();
                }

                $select->reset(Select::WHERE)
                    ->where('IFNULL(sv.value, dv.value) IN (?)', $productModel->getVisibleInSiteVisibilities())
                    ->where("vuk.attribute_id = ?", $this->getProductAttributeUrlKeyId())
                    ->where("vuk.{$this->idColumn} > ?", $productId);
                $products = $connection->fetchAll($select);
            }

            $this->progressBar->finish();
            $this->output->write('', true);
        }

        $microTimeEnd = microtime(true);
        $microTimeDiff = $microTimeEnd - $microTimeStart;
        $this->output->write('         Successfully regenerated URL rewrites for ' . $counter . ' products in '
            . $this->formatTime($microTimeDiff), true);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->setupProgress();

        try {
            $globalMicroTimeStart = microtime(true);

            $this->init();
            $this->truncateTables();
            $this->rebuildCmsUrlRewrites();
            $this->checkCategoryUrlKeys();
            $this->checkProductUrlKeys();
            $this->rebuildCategoryUrlRewrites();
            $this->rebuildProductUrlRewrites();

            $globalMicroTimeEnd = microtime(true);
            $microTimeDiff = $globalMicroTimeEnd - $globalMicroTimeStart;

            if ($this->errors) {
                $errorMessage = "ERRORS: \n";
                $entities = [];
                foreach ($this->errors as $entity => $error) {
                    $entities[] = $entity;
                    $message = "========== {$entity} ==========\n{$error}\n===============================\n\n";
                    $errorMessage .= $message;
                }

                $errorMessage .= '[WARNING] Please edit URL key manually for ' . implode("\n", $entities) . "\n";

                error_log(self::LOG_FILE);
                $this->output->write($errorMessage, true);
            }

            $this->output->write('', true);
            $this->output->write('[FINISH] All system URL rewrites were rebuilt successfully in '
                . $this->formatTime($microTimeDiff), true);

            $this->output->write('', true);
        } catch (\Exception $e) {
            $this->output->writeln($e->getMessage());
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}
