# Sparta_UrlRewriteRebuilder supposed to regenerate URL rewrites
## WARNINGS:
- It\`s STRONGLY NOT RECOMMENDED to KEEP *Sparta_UrlRewriteRebuilder* ENABLED on production when it\`s not used for rewrites rebuilding
- *Sparta_UrlRewriteRebuilder* tool CHANGES URL keys, URL pathes - PLEASE check changed data after running tool
- ALWAYS accurately test tool and check results (category and product URL key and URL path values, generated URL rewrites) on DEV COPY of production site on first
- ALWAYS CREATE BACKUPS before running rebuild rewrites on PRODUCTION

## Installation
```
git clone --branch 2.2.6 git@github.com:ydenyshchenk/Sparta_UrlRewriteRebuilder.git app/code/Sparta/UrlRewriteRebuilder
bin/magento module:enable Sparta_UrlRewriteRebuilder
bin/magento setup:upgrade
chmod -R 777 var
```
Please use the next branches for your Magento version: 2.1.9, 2.1.15, 2.2.6

## Usage
```sh
$ php bin/magento sparta:rebuild:rewrite -h
Usage:
 sparta:rebuild:rewrites [-m|--mode[="..."]]

Options:
--mode (-m)           Mode: truncate or delete (default: "delete")
```


```sh
$ php bin/magento sparta:rebuild:rewrites
[STEP 1] Truncating tables `catalog_url_rewrite_product_category` and `url_rewrite`... performed successfully in 00:00:00
[STEP 2] Rebuilding URL rewrites for CMS pages... performed successfully in 00:00:01
[STEP 3] Checking for duplicate url_key category values
         Found 0 duplicate url keys
         0 duplicates of url_key category values were processed in 00:00:00
[STEP 4] Checking for duplicate url_key product values
         Found 0 duplicate url keys
         0 duplicates of url_key product values were processed in 00:00:01
[STEP 5] Rebuilding URL rewrites for categories... 
         Rebuilding URL rewrites for categories 641/641 [============================] 100%
         Successfully regenerated URL rewrites for 641 categories and linked products in 00:00:25
[STEP 6] Rebuilding URL rewrites for products... 
         Rebuilding URL rewrites for products 16306/16306 [============================] 100%
         Successfully regenerated URL rewrites for 16259 products in 00:05:05

[FINISH] All system URL rewrites were rebuilt successfully in 00:05:34
```
