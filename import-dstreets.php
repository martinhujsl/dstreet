<?php
// Disable output buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

// Clean any existing buffers
while (@ob_end_clean());

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
echo str_repeat(' ', 1024 * 4); // 4KB buffer fill
flush();

require_once('prestashopImportClass.php');
require 'vendor/autoload.php';

$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '');

if ($debugFilter) {
	echo '<h2>DStreet Import Process</h2>';
	echo '<p>Debug mode enabled for code: ' . htmlspecialchars($_GET['debug_code']) . '</p>';
	echo '<p><strong>Starting import...</strong></p>';
	flush();
}

$startTime = microtime(true);
echo '<p>Script started at: ' . date('H:i:s') . '</p>';
flush();

$importClass = new PrestaShopWebsSrvicesImportClass();

// Step 1: Get product references from eshop
$importClass->getProductReferencesFromEshopByManufacturer(MANUFACTURER_ID);

// Step 2: Process XML file
$importClass->xmlToArray(SOURCE_XML_FILE);

// Step 3: Create XML for products not found
$importClass->createNotFindProductXML();

// Step 4: Prepare product IDs - remove leading pipe if present
if (!empty($importClass->productIds) && $importClass->productIds[0] === '|') {
	$importClass->productIds = substr($importClass->productIds, 1);
}

if ($debugFilter) {
	echo '<br>[DEBUG MAIN] Product IDs after processing: "' . $importClass->productIds . '"';
	echo '<br>[DEBUG MAIN] Number of products to process: ' . (empty($importClass->productIds) ? 0 : count(explode('|', $importClass->productIds))) . '<br>';
}

// Step 5: Get stock available IDs with all required fields
$importClass->getStockAvailableIds();

// Step 6: Create XML files for updates/:
$importClass->createCombinationsAndProductsXmlUpdateApiFiles();

// Display total execution time
$endTime = microtime(true);
$totalTime = $endTime - $startTime;
echo '<br><br>======================================';
echo '<br><strong style="color: #0066cc;">EXECUTION TIME</strong>';
echo '<br>======================================';
echo '<br>Total script execution time: <strong>' . number_format($totalTime, 2) . ' seconds</strong>';
echo '<br>Script finished at: ' . date('H:i:s');
echo '<br>======================================';

//die();

// Step 7: AUTO-UPDATE via API (NEW - using working test logic)
echo '<br><br><strong>Step 6: Updating stock availables via API...</strong>';
echo '<br>(This may take several minutes depending on the number of updates...)';
flush();

// Add error handling
try {
	$resultStock = $importClass->updateStockAvailablesViaAPI();
	echo '<br>✓ Stock availables updated';
	flush();
} catch (Exception $e) {
	echo '<br><strong style="color:red;">ERROR in stock API update: ' . $e->getMessage() . '</strong>';
	echo '<br>Stack trace: <pre>' . $e->getTraceAsString() . '</pre>';
	$resultStock = ['success' => 0, 'errors' => 1];
}

// Step 8: Update products (visibility/active)
echo '<br><br><strong>Step 7: Updating products via API...</strong>';
flush();
try {
	$resultProducts = $importClass->updateProductsViaAPI();
	echo '<br>✓ Products updated';
	flush();
} catch (Exception $e) {
	echo '<br><strong style="color:red;">ERROR in products API update: ' . $e->getMessage() . '</strong>';
	echo '<br>Stack trace: <pre>' . $e->getTraceAsString() . '</pre>';
	$resultProducts = ['success' => 0, 'errors' => 1];
}

if ($debugFilter) {
	echo '<br><br><h3>FINAL SUMMARY:</h3>';
	echo '<ul>';
	echo '<li>Stock updates successful: ' . $resultStock['success'] . '</li>';
	echo '<li>Stock update errors: ' . $resultStock['errors'] . '</li>';
	echo '<li>Product updates successful: ' . $resultProducts['success'] . '</li>';
	echo '<li>Product update errors: ' . $resultProducts['errors'] . '</li>';
	echo '</ul>';
	echo '<p><strong>Import completed!</strong></p>';
} else {
	echo 'Import completed: Stock: ' . $resultStock['success'] . ' updated, ' . $resultStock['errors'] . ' errors | Products: ' . $resultProducts['success'] . ' updated, ' . $resultProducts['errors'] . ' errors';
}
