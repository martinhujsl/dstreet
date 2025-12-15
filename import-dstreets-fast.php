<?php
/**
 * Fast import - generates XML only, without API update
 * Run sendXmlFileToAPI.php separately after this
 */
require_once('prestashopImportClass.php');
require 'vendor/autoload.php';

$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '');

if ($debugFilter) {
	echo '<h2>DStreet Import Process (Fast Mode)</h2>';
	echo '<p>Debug mode enabled for code: ' . htmlspecialchars($_GET['debug_code']) . '</p>';
	echo '<p><strong>Note:</strong> This version generates XML files only. Run sendXmlFileToAPI.php separately to update PrestaShop.</p>';
}

$importClass = new PrestaShopWebsSrvicesImportClass();

// Step 1: Get product references from eshop
$importClass->getProductReferencesFromEshopByManufacturer(MANUFACTURER_ID);

// Step 2: Process XML file
$importClass->xmlToArray('../../download/dstreet_full.xml');

// Step 3: Create XML for products not found
$importClass->createNotFindProductXML();

// Step 4: Prepare product IDs
$importClass->productIds = substr($importClass->productIds, 1);

// Step 5: Get stock available IDs with all required fields
$importClass->getStockAvailableIds();

// Step 6: Create XML files for updates
$importClass->createCombinationsAndProductsXmlUpdateApiFiles();

if ($debugFilter) {
	echo '<br><br><h3>XML Generation Complete!</h3>';
	echo '<p>Next step: <a href="sendXmlFileToAPI.php">Click here to run API update</a></p>';
} else {
	echo 'XML generation completed. Run sendXmlFileToAPI.php to update PrestaShop.';
}
