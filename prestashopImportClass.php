<?php
require_once 'iniSets.cfg.php';
require_once 'defines.cfg.php';
require_once('PSWebServiceLibrary.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx; 
/**
 * Description of prestashopImportClass
 *
 * @author admin
 */
class PrestaShopWebsSrvicesImportClass {

	private $api;
	public $productIds;
	public $productsReferences;
	private $stockAvailableIds;
	private $diffArray;
	private $codesConversion;

	function __construct() {
		$this->initPrestashopWebSarvices();
		$this->loadCodesConversion();
	}

	private function loadCodesConversion(){
		require_once 'codesConversion.cfg.php';
		$this->codesConversion = $codesConversion;
	}

	private function initPrestashopWebSarvices() {
		$this->api = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
	}

	function getProductReferencesFromEshopByManufacturer($manufacturerId) {

		$opt = [
		    'resource' => 'products',
		    'filter[id_manufacturer]' => $manufacturerId,
		    'display' => '[id,reference,product_type]'
		];

		$xml = $this->api->get($opt);
		$products = $xml->products->children();

		foreach ($products as $product) {
			//echo('<br>'.$product->reference);		
			$this->productsReferences[preg_replace('/ skl./','',trim(strtolower((string) $product->reference)))] = (string) $product->id;
		}
	}

	function xmlToArray($xmlFileName) {

		$xmlRemoteSource = simplexml_load_file($xmlFileName, null, LIBXML_NOBLANKS);
		$remoteProducts = $xmlRemoteSource->children()->children();
		$i = 0;
		$output = array();
		foreach ($remoteProducts as $remoteProduct) {
			$codeOnCard = (string) $remoteProduct->attributes()->code_on_card; //['code_on_card'];
			if (!isset($this->productsReferences[$codeOnCard])) {
				continue;
			} else {
				$this->productIds .= "|" . $this->productsReferences[trim((string) $codeOnCard)];
				unset($this->productsReferences[trim((string) $codeOnCard)]);
			}
			foreach ((array) $remoteProduct as $elementName => $value) {

				if ($elementName == 'sizes') {
					foreach ($remoteProduct->sizes->size as $size) {
						$sizeId = (string) $size->attributes()['id']; 
						@$sizeCode = $this->getPrestashopSizeCode($sizeId);
						//@$sizeCode = $this->codesConversion[$sizeId];
						@$sizeQuantity = $size->stock->attributes()['quantity'];
						$output[(string) $codeOnCard . '-' . (string) $sizeCode] = (string) $sizeQuantity;
					}
				}
			}
			$i++;
		}
		//print_r($output);
		$this->diffArray = $output;
	}

	private function getPrestashopSizeCode($sizeId){
		foreach ($this->codesConversion as $cv_key => $cv_val )
		{
			if ( (string)$sizeId == (string)$cv_key )
			{
				$sizeId = $cv_val;
			}	
		}	
		return $sizeId;	
	}

	function getStockAvailableIds() {
		//echo '<br>getstosck products ids:'.$this->productIds;
		$opt = [
		    'resource' => 'stock_availables',
		    '[' . $this->productIds . ']',
		    'display' => '[id,id_product,id_product_attribute]'
		];

		$xml = $this->api->get($opt);
		//echo '<br>count stock:'.count((array)$xml->stock_availables->children());
		foreach ($xml->stock_availables->children() as $stockAvailableObject) {
			//print_r($stockAvailableObject);
			$id_product_attribute = $stockAvailableObject->id_product_attribute;
			if ($id_product_attribute > 0) {

				//echo '<br>avail:'.$id_product_attribute;
				$this->stockAvailableIds[(int) $id_product_attribute] = (int) $stockAvailableObject->id;
			}
		}
	}

	function createNotFindProductXML(){
		$xmlProductSet = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		foreach ($this->productsReferences as $productId) {
			$productVisibilityXML = $xmlProductSet->addChild('product');
			$productVisibilityXML->addChild('id', $productId);
			$productVisibilityXML->addChild('visibility', 'search');
			$productVisibilityXML->addChild('active', '0');			
		}
		$xmlProductSet->asXML(PRODUCTS_NOT_FIND);	
	}
	
	function createCombinationsAndProductsXmlUpdateApiFiles() {
		$opt = [
		    'resource' => 'combinations',
		    '[' . $this->productIds . ']',
		    'sort' => '[reference_DESC]',
		    'display' => '[id,id_product,reference]'
		];
		$productsReferencesById = array_flip($this->productsReferences);
		//print_r($productsReferencesById);
		$spreadsheet = new Spreadsheet();
		$spreadsheet->createSheet();
		// Zero based, so set the second tab as active sheet
		
		$spreadsheet->setActiveSheetIndex(1)->setTitle('Second tab');
		$spreadsheet->createSheet();
		// Zero based, so set the second tab as active sheet
		
		$spreadsheet->setActiveSheetIndex(2)->setTitle('dalsi tab');		
		//$activeWorksheet = $spreadsheet->getActiveSheet();
		$xml = $this->api->get($opt);
		$xmlStockAvailables = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		$xmlProductSet = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		$totalQuantity = 0;
		$lastProductId = 0;
		$i = 1;
		$j = 1;
		//echo '<br>count stock:'.count((array)$xml->combinations);
		foreach ($xml->combinations->children() as $combination) {
			$combinationReference = $combination->reference;
		//	echo '<br>combination reference:'.$combinationReference;
			$productId = $combination->id_product;
			$combinationId = (int) $combination->id;
			@$stockavAilablesId = $this->stockAvailableIds[$combinationId];
			if(isset($this->diffArray[(string) $combinationReference])){
				$combinationQuantity = $this->diffArray[(string) $combinationReference];
			} else {
				$combinationQuantity = '';
				$activeWorksheet = $spreadsheet->setActiveSheetIndex(1);
				$activeWorksheet->setCellValue('A'.$i, $productId);
				@$activeWorksheet->setCellValue('B'.$i, $productsReferencesById[(int)$productId]);
				$activeWorksheet->setCellValue('C'.$i, (string) $combinationReference);
				$i++;
			}	
			if ($combinationQuantity != '') {
				$setVisibility = 'both';
				if ((int) $combinationQuantity < 2) {
					$combinationQuantity = 0;
				}
				if ($lastProductId == (int) $productId || $lastProductId == 0) {
					$totalQuantity += (int) $combinationQuantity;
				} else {
		//			echo "\n" . 'vytvarim nastaveni produktu';
					if ($totalQuantity > 1) {
						$productVisibilityXML = $xmlProductSet->addChild('product');
						$productVisibilityXML->addChild('id', $lastProductId);
						$productVisibilityXML->addChild('visibility', 'both');
						$productVisibilityXML->addChild('active', '1');
						$setVisibility = 'both';
					} else {
						//echo "\n" . 'nastavuji search pro id_product:' . $lastProductId;
						$productVisibilityXML = $xmlProductSet->addChild('product');
						$productVisibilityXML->addChild('id', $lastProductId);
						$productVisibilityXML->addChild('visibility', 'search');
						$productVisibilityXML->addChild('active', '1');
						$setVisibility = 'search';
					}
					$totalQuantity = (int) $combinationQuantity;
				}
				$lastProductId = $productId;
				//echo "\n" . 'product id:' . $productId;
				//echo "\n" . 'combination id:' . $combinationId;
				//echo "\n" . 'stock_availables id:' . $stockavAilablesId;
				//echo "\n" . 'combination refrence:' . $combinationReference;
				//echo "\n" . 'quantity:' . $combinationQuantity;
				//echo "\n" . 'total:' . $totalQuantity;
				//echo "\n" . 'lastProcutId::' . $lastProductId;
				$activeWorksheet = $spreadsheet->setActiveSheetIndex(2);
				$activeWorksheet->setCellValue('A'.$j, $productId);
				$activeWorksheet->setCellValue('B'.$j, $combinationId);
				$activeWorksheet->setCellValue('C'.$j, (string) $combinationReference);
				$activeWorksheet->setCellValue('D'.$j, (string) $combinationQuantity);
				$activeWorksheet->setCellValue('E'.$j, $setVisibility);
				$j++;
				$stock_available = $xmlStockAvailables->addChild('stock_available');
				$stock_available->addChild('id', $stockavAilablesId);
				$stock_available->addChild('quantity', (int) $combinationQuantity);
			}
		}
		$writer = new Xlsx($spreadsheet);
		$writer->save('notFindedCombinations.xlsx');
		$xmlStockAvailables->asXML(STOCK_AVAILABLES_UPDATE);
		$xmlProductSet->asXML(PRODUCTS_UPDATE);
	}
}
