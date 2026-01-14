<?php
//ini_set('max_execution_time', '0');
//ini_set( 'memory_limit', '2024M' );
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
//define('DEBUG', false);											// Debug mode
//define('PS_SHOP_PATH', 'http://test2.mariti.sk/');		// Root path of your PrestaShop store
//define('PS_WS_AUTH_KEY', 'TRRVDNJ75X1FT9FPYZJVZ829GFZIMFY9');	// Auth key (Get it in your Back Office)
require_once 'iniSets.cfg.php';

// Load production config if exists, otherwise use default
if (file_exists(__DIR__ . '/defines-prod.cfg.php')) {
    require_once 'defines-prod.cfg.php';
} else {
    require_once 'defines.cfg.php';
}

require_once('PSWebServiceLibrary.php');
$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);

$xmlStockSource = simplexml_load_file(STOCK_AVAILABLES_UPDATE, null, LIBXML_NOBLANKS);

try
{
	$xml = $xmlStockSource->asXML();
	$opt = array('resource' => 'stock_availables');
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
