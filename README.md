# Sparta_UrlRewriteRebuilder supposed to regenerate URL rewrites

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
         Rebuilding URL rewrites for products  6425/16306 [===========>----------------]  39%
[ERROR] URL key for specified store already exists. 
         Rebuilding URL rewrites for products 16306/16306 [============================] 100%
         Successfully regenerated URL rewrites for 16259 products in 00:05:05

[FINISH] All system URL rewrites were rebuilt successfully in 00:05:34
```