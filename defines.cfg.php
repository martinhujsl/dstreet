<?php
define('DEBUG', false);	   // Debug mode
define('PS_SHOP_PATH', 'http://mariti.sk');  // Root path of your PrestaShop store
//Klic produkce:
define('PS_WS_AUTH_KEY', '4ABKPB18AYWMTCLX6FDK9239B99XBEPX'); // Auth key (Get it in your Back Office)
//Klic test2
//define('PS_WS_AUTH_KEY', 'TRRVDNJ75X1FT9FPYZJVZ829GFZIMFY9'); // Auth key (Get it in your Back Office)
define('MANUFACTURER_ID', 7);
define('ID_SHOP', 1); // ID of the shop in multistore (use 1 for default, or specific shop ID)
define('SOURCE_XML_FILE', '../../download/dstreet_full.xml'); // Path to source XML file
define('PRODUCTS_NOT_FIND', 'webservicesxml/products_notfind.xml');
define('PRODUCTS_UPDATE', 'webservicesxml/products_update.xml');
define('STOCK_AVAILABLES_UPDATE', 'webservicesxml/stock_availables_update.xml');

// API Batch Sizes - Adjust these for optimal performance
// Larger values = fewer API calls = faster loading
// Too large values may cause timeouts or memory issues
// Run test-batch-sizes.php to find optimal values for your server
define('BATCH_SIZE_STOCK_AVAILABLES', 30000);  // Optimal: 30000 (4,671 items/sec from test)
define('BATCH_SIZE_COMBINATIONS', 50000);      // Optimal: 50000 (4,089 items/sec from test)
