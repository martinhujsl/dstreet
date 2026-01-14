<?php
require_once('prestashopImportClass.php');
require 'vendor/autoload.php';
$importClass = new PrestaShopWebsSrvicesImportClass();
$importClass->getProductReferencesFromEshopByManufacturer(MANUFACTURER_ID);
//echo '<pre>';
//print_r($importClass->productsReferences);
$importClass->xmlToArray('../../download/dstreet_full.xml', $importClass->productsReferences);
$importClass->createNotFindProductXML();
$importClass->productIds = substr($importClass->productIds, 1);
//echo '<br>products ids:' . $importClass->productIds;
$importClass->getStockAvailableIds();
$importClass->createCombinationsAndProductsXmlUpdateApiFiles();
//echo '</pre>';
