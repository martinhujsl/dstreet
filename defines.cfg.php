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
define('ID_SHOP', 1); // ID of the shop in multistore (use 1 for default, or specific shop ID)
define('SOURCE_XML_FILE', '/path/to/your/source/dstreet_full.xml'); // Path to source XML file
define('PRODUCTS_NOT_FIND', 'webservicesxml/products_notfind.xml');
define('PRODUCTS_UPDATE', 'webservicesxml/products_update.xml');
define('STOCK_AVAILABLES_UPDATE', 'webservicesxml/stock_availables_update.xml');

// API Batch Sizes - Adjust these for optimal performance
// Larger values = fewer API calls = faster loading
// Too large values may cause timeouts or memory issues
// Run test-batch-sizes.php to find optimal values for your server
define('BATCH_SIZE_STOCK_AVAILABLES', 30000);  // Optimal: 30000 (4,671 items/sec from test)
define('BATCH_SIZE_COMBINATIONS', 50000);      // Optimal: 50000 (4,089 items/sec from test)
