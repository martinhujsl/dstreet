<?php
/**
 * Configuration file for PrestaShop import
 * 
 * IMPORTANT: For production use, copy this file to defines-prod.cfg.php
 * and update with your actual credentials. The defines-prod.cfg.php file
 * will be used automatically if it exists and won't be tracked by git.
 */

define('DEBUG', false);	   // Debug mode
define('PS_SHOP_PATH', 'http://your-prestashop-store.com/');  // Root path of your PrestaShop store
define('PS_WS_AUTH_KEY', 'YOUR_API_KEY_HERE'); // Auth key (Get it in your Back Office)
define('MANUFACTURER_ID', 7); // Your manufacturer ID
define('PRODUCTS_NOT_FIND', 'webservicesxml/products_notfind.xml');
define('PRODUCTS_UPDATE', 'webservicesxml/products_update.xml');
define('STOCK_AVAILABLES_UPDATE', 'webservicesxml/stock_availables_update.xml');
