<?php
ini_set('max_execution_time', '0');
ini_set( 'memory_limit', '2024M' );
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Optional: Add simple security check
// Uncomment the lines below to require a password parameter
// if (!isset($_GET['run']) || $_GET['run'] !== 'yes') {
//     die('Access denied. Add ?run=yes to URL to execute.');
// }

//define('DEBUG', false);											// Debug mode
//define('PS_SHOP_PATH', 'http://test2.mariti.sk/');		// Root path of your PrestaShop store
//define('PS_WS_AUTH_KEY', 'TRRVDNJ75X1FT9FPYZJVZ829GFZIMFY9');	// Auth key (Get it in your Back Office)
require_once 'iniSets.cfg.php';
require_once 'defines.cfg.php';
require_once('PSWebServiceLibrary.php');

// Determine if running from CLI or browser
$isCLI = (php_sapi_name() === 'cli');
$debugMode = isset($_GET['debug']) || $isCLI;

$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, $debugMode);

$xmlStockSource = simplexml_load_file(STOCK_AVAILABLES_UPDATE, null, LIBXML_NOBLANKS);

echo "<h3>Updating stock_availables...</h3>";
$stockAvailables = $xmlStockSource->stock_available;
$totalCount = count($stockAvailables);
$successCount = 0;
$errorCount = 0;

echo "Found {$totalCount} stock_available(s) to update...<br><br>";

// PrestaShop 8 requires individual updates for each stock_available
foreach ($stockAvailables as $stockAvailable) {
	$stockId = (int) $stockAvailable->id;
	$quantity = (int) $stockAvailable->quantity;

	echo "Updating stock_available ID: {$stockId} → Quantity: {$quantity}... ";

	try {
		// Get current stock_available data from API
		$currentOpt = [
			'resource' => 'stock_availables',
			'id' => $stockId
		];
		$currentXml = $webService->get($currentOpt);

		// Update only the quantity field
		$currentXml->stock_available->quantity = $stockAvailable->quantity;

		// Send PATCH without 'id' parameter (PrestaShop 8 requirement)
		$opt = array(
			'resource' => 'stock_availables',
			// NOTE: No 'id' here! ID is in XML only, not in URL
			'putXml' => $currentXml->asXML(),
			'request_type' => 'PATCH'
		);

		$xmlOutput = $webService->edit($opt);
		echo "<strong style='color:green;'>✓ OK</strong><br>";
		$successCount++;

	} catch (PrestaShopWebserviceException $ex) {
		$trace = $ex->getTrace();
		echo "<strong style='color:red;'>✗ FAILED</strong> - ";
		if ($trace[0]['args'][0] == 404) echo 'Bad ID';
		else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
		else echo $ex->getMessage();
		echo "<br>";
		$errorCount++;
	}
}

echo "<br><strong>Summary:</strong> {$successCount} updated, {$errorCount} errors<br><br>";

$xmlProductsSource = simplexml_load_file(PRODUCTS_NOT_FIND, null, LIBXML_NOBLANKS);

try
{
	$xml = $xmlProductsSource->asXML();
	$opt = array('resource' => 'products');
	$opt['putXml'] = $xml;
	$opt['request_type'] = 'PATCH';
	//$opt['id'] = (int)$stock_available_id;
	
	//print_r($opt);
	$xmlOutput = $webService->edit($opt);
	
	// if WebService don't throw an exception the action worked well and we don't show the following message
	echo "Successfully updated.";
}
catch (PrestaShopWebserviceException $ex)
{
	// Here we are dealing with errors
	$trace = $ex->getTrace();
	if ($trace[0]['args'][0] == 404) echo 'Bad ID';
	else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
	else echo 'Other error<br />'.$ex->getMessage();
}						

$xmlProductsSource = simplexml_load_file(PRODUCTS_UPDATE, null, LIBXML_NOBLANKS);

try
{
	$xml = $xmlProductsSource->asXML();
	$opt = array('resource' => 'products');
	$opt['putXml'] = $xml;
	$opt['request_type'] = 'PATCH';
	//$opt['id'] = (int)$stock_available_id;
	
	//print_r($opt);
	$xmlOutput = $webService->edit($opt);
	
	// if WebService don't throw an exception the action worked well and we don't show the following message
	echo "Successfully updated.";
}
catch (PrestaShopWebserviceException $ex)
{
	// Here we are dealing with errors
	$trace = $ex->getTrace();
	if ($trace[0]['args'][0] == 404) echo 'Bad ID';
	else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
	else echo 'Other error<br />'.$ex->getMessage();
}						