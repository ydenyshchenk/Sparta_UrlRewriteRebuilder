<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sparta\UrlRewriteRebuilder\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\UrlRewrite\Model\Storage\DbStorage as MagentoDbStorage;

/**
 * @inheritdoc
 */
class DbStorage extends MagentoDbStorage
{
    /**
     * {@inheritdoc}
     */
    public function replace(array $urls)
    {
        if (!$urls) {
            return;
        }

        try {
            $this->doReplace($urls);
        } catch (AlreadyExistsException $e) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function insertMultiple($data)
    {
        try {
            $this->connection->insertMultiple($this->resource->getTableName(self::TABLE_NAME), $data);
        } catch (\Exception $e) {
            if ($e->getCode() === self::ERROR_CODE_DUPLICATE_ENTRY
                && preg_match('#SQLSTATE\[23000\]: [^:]+: 1062[^\d]#', $e->getMessage())
            ) {
                $message = $e->getMessage();
                preg_match("/Duplicate\sentry\s\'([^\']+)\'\sfor/", $message, $matches);
                $messages = explode(', query was:', $message);

                $newMessage = 'URL key for specified store already exists: ';
                if (!empty($matches[1])) {
                    $requestPath = $matches[1];
                    $newMessage .= "execute the next SQL to find duplicate URL rewrite: SELECT * FROM `url_rewrite` WHERE CONCAT(`request_path`, '-', `store_id`) = '$requestPath';";
                } else {
                    $newMessage .= $messages[0];
                }

                throw new AlreadyExistsException(__($newMessage));
            }
            throw $e;
        }
    }
}
