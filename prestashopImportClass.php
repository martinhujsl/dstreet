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
	private $productsCache; // Cache for current product states (visibility, active)

	function __construct() {
		$this->productIds = ''; // Initialize to empty string
		$this->productsReferences = [];
		$this->stockAvailableIds = [];
		$this->diffArray = [];
		$this->productsCache = []; // Initialize products cache
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
		    'display' => '[id,reference,product_type,visibility,active,available_for_order]'
		];

		$xml = $this->api->get($opt);
		$products = $xml->products->children();

		foreach ($products as $product) {
			//echo('<br>'.$product->reference);
			$cleanedReference = preg_replace('/ skl./','',trim(strtolower((string) $product->reference)));
			$productId = (string) $product->id;
			$this->productsReferences[$cleanedReference] = $productId;
			
			// Cache current product state for comparison later
			$this->productsCache[$productId] = [
				'visibility' => (string)$product->visibility,
				'active' => (string)$product->active,
				'available_for_order' => (string)$product->available_for_order
			];
			
			// Debug: Show product references from eshop
			if (isset($_GET['debug_code']) && $_GET['debug_code'] !== '') {
				if (stripos($cleanedReference, strtolower($_GET['debug_code'])) !== false) {
					echo '<br>[DEBUG ESHOP] Found in eshop - Reference: ' . $cleanedReference . ' | Product ID: ' . $product->id . ' | Original: ' . $product->reference;
					echo ' | Visibility: ' . $product->visibility . ' | Active: ' . $product->active;
				}
			}
		}
		
		if (isset($_GET['debug_code']) && $_GET['debug_code'] !== '') {
			echo '<br>[DEBUG ESHOP] Total products loaded from eshop: ' . count($this->productsReferences);
			echo '<br>[DEBUG ESHOP] Products cache size: ' . count($this->productsCache);
		}
	}

	function xmlToArray($xmlFileName) {

		$xmlRemoteSource = simplexml_load_file($xmlFileName, null, LIBXML_NOBLANKS);
		if ($xmlRemoteSource === false) {
			echo '<br><strong style="color:red;">ERROR: Cannot load XML file: ' . $xmlFileName . '</strong>';
			$errors = libxml_get_errors();
			foreach ($errors as $error) {
				echo '<br>Error: ' . $error->message;
			}
			libxml_clear_errors();
			die('Cannot proceed without XML file');
		}
		$remoteProducts = $xmlRemoteSource->children()->children();
		$i = 0;
		$output = array();
		$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '') ? strtolower($_GET['debug_code']) : '';
		
		foreach ($remoteProducts as $remoteProduct) {
			$codeOnCard = (string) $remoteProduct->attributes()->code_on_card; //['code_on_card'];
			$codeOnCardLower = strtolower(trim($codeOnCard));
			
			// If debug filter is active, process ONLY matching products
			if ($debugFilter !== '' && stripos($codeOnCardLower, $debugFilter) === false) {
				continue; // Skip products that don't match the debug filter
			}
			
			// Debug: Show product from XML file
			if ($debugFilter !== '') {
				echo '<br>[DEBUG XML] Processing product from XML - code_on_card: "' . $codeOnCard . '"';
				echo '<br>[DEBUG XML] Cleaned code_on_card: "' . $codeOnCardLower . '"';
				echo '<br>[DEBUG XML] Checking if exists in productsReferences...';
				
				if (isset($this->productsReferences[$codeOnCardLower])) {
					echo '<br>[DEBUG XML] ✓ FOUND in productsReferences! Product ID: ' . $this->productsReferences[$codeOnCardLower];
				} else {
					echo '<br>[DEBUG XML] ✗ NOT FOUND in productsReferences!';
					echo '<br>[DEBUG XML] Available references (sample): ';
					$sampleRefs = array_slice(array_keys($this->productsReferences), 0, 10);
					echo '<br>' . implode('<br>', $sampleRefs);
				}
			}
			
			if (!isset($this->productsReferences[$codeOnCardLower])) {
				// Debug: Product not found
				if ($debugFilter !== '') {
					echo '<br>[DEBUG XML] Product SKIPPED - not in eshop';
				}
				continue;
			} else {
				$this->productIds .= "|" . $this->productsReferences[$codeOnCardLower];
				
				// Debug: Product found and added
				if ($debugFilter !== '') {
					echo '<br>[DEBUG XML] Product ADDED to processing list';
				}
				
				unset($this->productsReferences[$codeOnCardLower]);
			}
			foreach ((array) $remoteProduct as $elementName => $value) {

				if ($elementName == 'sizes') {
					foreach ($remoteProduct->sizes->size as $size) {
						$sizeId = (string) $size->attributes()['id']; 
						@$sizeCode = $this->getPrestashopSizeCode($sizeId);
						//@$sizeCode = $this->codesConversion[$sizeId];
						@$sizeQuantity = $size->stock->attributes()['quantity'];
						$sizeCodeLower = strtolower(trim((string) $sizeCode));
						$output[(string) $codeOnCardLower . '-' . $sizeCodeLower] = (string) $sizeQuantity;
						
						// Debug: Show sizes
						if ($debugFilter !== '') {
							echo '<br>[DEBUG XML] Size: ' . $sizeCode . ' (key: ' . $codeOnCardLower . '-' . $sizeCodeLower . ') | Quantity: ' . $sizeQuantity;
						}
					}
				}
			}
			$i++;
		}
		
		if ($debugFilter !== '') {
			echo '<br>[DEBUG XML] Total products processed from XML: ' . $i;
			echo '<br>[DEBUG XML] ================================================';
		} else {
			// Show stats even without debug filter
			echo '<br>Processed ' . $i . ' products from XML file, created ' . count($output) . ' size combinations';
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
		// Load ALL stock_availables using pagination (including current quantity for comparison)
		echo '<br>[STOCK DEBUG] Loading all stock_availables...';
		$this->stockAvailableIds = [];
		$offset = 0;
		$limit = BATCH_SIZE_STOCK_AVAILABLES; // Use configurable batch size from defines
		$loadedCount = 0;

		do {
			$opt = [
				'resource' => 'stock_availables',
				'display' => '[id,id_product,id_product_attribute,id_shop,id_shop_group,depends_on_stock,out_of_stock,quantity]',
				'limit' => $offset . ',' . $limit
			];

			try {
				$xml = $this->api->get($opt);
				$batchCount = 0;

				if (isset($xml->stock_availables)) {
					foreach ($xml->stock_availables->children() as $stock) {
						$id_product_attribute = (int)$stock->id_product_attribute;
						// Index by id_product_attribute for quick lookup (including current quantity)
						$this->stockAvailableIds[$id_product_attribute] = [
							'id' => (int)$stock->id,
							'id_product' => (int)$stock->id_product,
							'id_product_attribute' => $id_product_attribute,
							'id_shop' => (int)$stock->id_shop,
							'id_shop_group' => (int)$stock->id_shop_group,
							'depends_on_stock' => (int)$stock->depends_on_stock,
							'out_of_stock' => (int)$stock->out_of_stock,
							'current_quantity' => (int)$stock->quantity  // Add current quantity for comparison
						];
						$batchCount++;
					}
				}

				echo '<br>[STOCK DEBUG] Loading batch at offset ' . $offset . ', got ' . $batchCount . ' stock_availables';

				$loadedCount += $batchCount;
				$offset += $limit;

			} catch (Exception $e) {
				echo '<br>[STOCK DEBUG] Error loading stock_availables: ' . $e->getMessage();
				break;
			}

		} while ($batchCount == $limit);

		echo '<br>[STOCK DEBUG] Total loaded: ' . $loadedCount . ' stock_availables';
	}

	function createNotFindProductXML(){
		$xmlProductSet = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		foreach ($this->productsReferences as $productId) {
			$productVisibilityXML = $xmlProductSet->addChild('product');
			$productVisibilityXML->addChild('id', $productId);
			$productVisibilityXML->addChild('visibility', 'search');
			$productVisibilityXML->addChild('active', '0');			
		}
		$xmlProductSet->asXML(__DIR__ . '/' . PRODUCTS_NOT_FIND);	
	}
	
	function createCombinationsAndProductsXmlUpdateApiFiles() {
		$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '');
		
		if ($debugFilter) {
			echo '<br><br>[DEBUG UPDATE] Creating combinations and products XML update files...';
		}

		$productsReferencesById = array_flip($this->productsReferences);

		$spreadsheet = new Spreadsheet();
		$spreadsheet->createSheet();
		$spreadsheet->setActiveSheetIndex(1)->setTitle('Second tab');
		$spreadsheet->createSheet();
		$spreadsheet->setActiveSheetIndex(2)->setTitle('dalsi tab');

		// Load ALL combinations using pagination (PrestaShop default limit is 50)
		// Use configurable batch size from defines
		$allCombinations = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop><combinations></combinations></prestashop>');
		$offset = 0;
		$limit = BATCH_SIZE_COMBINATIONS; // Use configurable batch size from defines
		$loadedCount = 0;

		do {
			$opt = [
				'resource' => 'combinations',
				'sort' => '[reference_DESC]',
				'display' => '[id,id_product,reference]',
				'limit' => $offset . ',' . $limit
			];

			$xml = $this->api->get($opt);
			$batchCount = 0;

			// Merge combinations from this batch and count them
			if (isset($xml->combinations)) {
				foreach ($xml->combinations->children() as $combination) {
					$newComb = $allCombinations->combinations->addChild('combination');
					$newComb->addChild('id', (string)$combination->id);
					$newComb->addChild('id_product', (string)$combination->id_product);
					$newComb->addChild('reference', (string)$combination->reference);
					$batchCount++;
				}
			}

			echo '<br>[BATCH DEBUG] Loading batch at offset ' . $offset . ', got ' . $batchCount . ' combinations';

			$loadedCount += $batchCount;
			$offset += $limit;

			// Stop when we get less than the limit (last page)
		} while ($batchCount == $limit);

		echo '<br>[BATCH DEBUG] Total loaded: ' . $loadedCount . ' combinations';

		$xml = $allCombinations;
		$totalCombinationsFound = $loadedCount; // Use actual loaded count, not count()

		if ($debugFilter) {
			echo '<br>[DEBUG UPDATE] Total combinations found: ' . $totalCombinationsFound;
			echo '<br>[DEBUG UPDATE] diffArray keys (from XML): ' . implode(', ', array_keys($this->diffArray));
		} else {
			echo '<br>Total combinations loaded from PrestaShop: ' . $totalCombinationsFound;
		}
		
		$xmlStockAvailables = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		$xmlProductSet = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
		$totalQuantity = 0;
		$lastProductId = 0;
		$i = 1;
		$j = 1;
		$processedCount = 0;
		
		// Create array of product IDs for quick lookup
		// productIds uses pipe separator: "96890|96891|96892..."
		if (empty($this->productIds)) {
			$productIdsArray = [];
		} else {
			$productIdsArray = explode('|', $this->productIds);
		}
		$productIdsLookup = array_flip($productIdsArray);

		echo '<br>[FILTER DEBUG] Product IDs to process: ' . count($productIdsArray);
		echo '<br>[FILTER DEBUG] diffArray size: ' . count($this->diffArray);

		if (!$debugFilter) {
			// Show first 10 diffArray keys to see what we're looking for
			$sampleKeys = array_slice(array_keys($this->diffArray), 0, 10);
			echo '<br>[FILTER DEBUG] Sample diffArray keys: ' . implode(', ', $sampleKeys);
		}

		$totalCombsChecked = 0;
		$totalPassedProductFilter = 0;
		$totalPassedDiffArrayFilter = 0;
		
		// Optimization statistics
		$stocksAdded = 0;
		$stocksSkipped = 0;
		$productsAdded = 0;
		$productsSkipped = 0;

		// Debug: Show how many combinations we're about to iterate
		$combinationsToProcess = $xml->combinations->children();
		$combinationsCount = count($combinationsToProcess);
		echo '<br>[FOREACH DEBUG] About to iterate over ' . $combinationsCount . ' combinations';

		foreach ($xml->combinations->children() as $combination) {
			$totalCombsChecked++;
			$combinationReference = $combination->reference;
			$combinationReferenceLower = strtolower(trim((string) $combinationReference));
			$productId = (int) $combination->id_product;
			$combinationId = (int) $combination->id;

			// Skip combinations not belonging to our manufacturer's products
			if (!isset($productIdsLookup[$productId])) {
				continue;
			}

			$totalPassedProductFilter++;

			if ($debugFilter) {
				echo '<br>[DEBUG UPDATE] Checking combination - Reference: "' . $combinationReference . '" | Lowercase: "' . $combinationReferenceLower . '" | Combination ID: ' . $combinationId;
			}

			// Skip combinations that are not in diffArray (no update needed)
			if (!isset($this->diffArray[$combinationReferenceLower])) {
				if ($debugFilter) {
					echo '<br>[DEBUG UPDATE] ✗ SKIPPED - not in diffArray';
				}
				continue;
			}

			$totalPassedDiffArrayFilter++;

			// Use pre-loaded stock_available data (much faster!)
			$stockAvailableData = null;
			if (isset($this->stockAvailableIds[$combinationId])) {
				$stockAvailableData = $this->stockAvailableIds[$combinationId];
			}
			
			$processedCount++;
			
			if ($debugFilter) {
				echo '<br>[DEBUG UPDATE] Processing combination - Reference: ' . $combinationReference . ' | Product ID: ' . $productId . ' | Combination ID: ' . $combinationId;
			}
			
			if(isset($this->diffArray[$combinationReferenceLower])){
				$combinationQuantity = $this->diffArray[$combinationReferenceLower];
				
				if ($debugFilter) {
					echo '<br>[DEBUG UPDATE] ✓ Found in diffArray | Quantity from XML: ' . $combinationQuantity;
				}
			} else {
				$combinationQuantity = '';
				$activeWorksheet = $spreadsheet->setActiveSheetIndex(1);
				$activeWorksheet->setCellValue('A'.$i, $productId);
				@$activeWorksheet->setCellValue('B'.$i, $productsReferencesById[(int)$productId]);
				$activeWorksheet->setCellValue('C'.$i, (string) $combinationReference);
				$i++;
				
				if ($debugFilter) {
					echo '<br>[DEBUG UPDATE] ✗ NOT found in diffArray - added to "not found" list';
				}
			}
			
			if ($combinationQuantity != '') {
				$setVisibility = 'both';
				if ((int) $combinationQuantity < 2) {
					$combinationQuantity = 0;
					if ($debugFilter) {
						echo '<br>[DEBUG UPDATE] Quantity < 2, setting to 0';
					}
				}
				
				if ($lastProductId == (int) $productId || $lastProductId == 0) {
					$totalQuantity += (int) $combinationQuantity;
				} else {
					// Determine desired product state based on total quantity
					$desiredVisibility = ($totalQuantity > 1) ? 'both' : 'search';
					$desiredActive = '1';
					$desiredAvailableForOrder = '1';
					
					// OPTIMIZATION: Only add to XML if product state needs to change
					$needsUpdate = false;
					if (isset($this->productsCache[$lastProductId])) {
						$currentState = $this->productsCache[$lastProductId];
						if ($currentState['visibility'] != $desiredVisibility || 
						    $currentState['active'] != $desiredActive || 
						    $currentState['available_for_order'] != $desiredAvailableForOrder) {
							$needsUpdate = true;
						}
					} else {
						// Product not in cache, add it to be safe
						$needsUpdate = true;
					}
					
					if ($needsUpdate) {
						$productVisibilityXML = $xmlProductSet->addChild('product');
						$productVisibilityXML->addChild('id', $lastProductId);
						$productVisibilityXML->addChild('visibility', $desiredVisibility);
						$productVisibilityXML->addChild('active', $desiredActive);
						$productVisibilityXML->addChild('available_for_order', $desiredAvailableForOrder);
						$productsAdded++;
						
						if ($debugFilter) {
							$oldState = isset($this->productsCache[$lastProductId]) ? 
								'vis:' . $this->productsCache[$lastProductId]['visibility'] . ',act:' . $this->productsCache[$lastProductId]['active'] : 'unknown';
							echo '<br>[DEBUG UPDATE] Product ' . $lastProductId . ' → ✓ ADDED (old: ' . $oldState . ', new: vis:' . $desiredVisibility . ',act:' . $desiredActive . ', qty: ' . $totalQuantity . ')';
						}
					} else {
						$productsSkipped++;
						if ($debugFilter) {
							echo '<br>[DEBUG UPDATE] Product ' . $lastProductId . ' → ⊘ SKIPPED (unchanged: vis:' . $desiredVisibility . ',act:' . $desiredActive . ', qty: ' . $totalQuantity . ')';
						}
					}
					
					$setVisibility = $desiredVisibility;
					$totalQuantity = (int) $combinationQuantity;
				}
				$lastProductId = $productId;
				
				$activeWorksheet = $spreadsheet->setActiveSheetIndex(2);
				$activeWorksheet->setCellValue('A'.$j, $productId);
				$activeWorksheet->setCellValue('B'.$j, $combinationId);
				$activeWorksheet->setCellValue('C'.$j, (string) $combinationReference);
				$activeWorksheet->setCellValue('D'.$j, (string) $combinationQuantity);
				$activeWorksheet->setCellValue('E'.$j, $setVisibility);
				$j++;

				// PrestaShop 8 requires all mandatory fields for PATCH update
				// OPTIMIZATION: Only add to XML if quantity has changed
				if (isset($stockAvailableData) && is_array($stockAvailableData)) {
					$currentQuantity = $stockAvailableData['current_quantity'];
					$newQuantity = (int) $combinationQuantity;
					
					// Only add to update XML if quantity is different
					if ($currentQuantity != $newQuantity) {
						$stock_available = $xmlStockAvailables->addChild('stock_available');
						$stock_available->addChild('id', $stockAvailableData['id']);
						$stock_available->addChild('id_product', $stockAvailableData['id_product']);
						$stock_available->addChild('id_product_attribute', $stockAvailableData['id_product_attribute']);
						$stock_available->addChild('id_shop', $stockAvailableData['id_shop']);
						$stock_available->addChild('id_shop_group', $stockAvailableData['id_shop_group']);
						$stock_available->addChild('quantity', $newQuantity);
						$stock_available->addChild('depends_on_stock', $stockAvailableData['depends_on_stock']);
						$stock_available->addChild('out_of_stock', $stockAvailableData['out_of_stock']);
						$stocksAdded++;

						if ($debugFilter) {
							echo '<br>[DEBUG UPDATE] ✓ Added to stock_availables XML: stock_available ID: ' . $stockAvailableData['id'] . ' | Old: ' . $currentQuantity . ' → New: ' . $newQuantity;
						}
					} else {
						$stocksSkipped++;
						if ($debugFilter) {
							echo '<br>[DEBUG UPDATE] ⊘ SKIPPED - quantity unchanged: ' . $currentQuantity;
						}
					}
				} else {
					if ($debugFilter) {
						echo '<br>[DEBUG UPDATE] ✗ WARNING: stock_available data not found for combination ID: ' . $combinationId;
					}
				}
			}
		}

		// Show filtering statistics
		echo '<br><br>[FILTER SUMMARY]';
		echo '<br>- Total combinations checked: ' . $totalCombsChecked;
		echo '<br>- Passed product ID filter: ' . $totalPassedProductFilter;
		echo '<br>- Passed diffArray filter: ' . $totalPassedDiffArrayFilter;
		echo '<br>- Actually processed: ' . $processedCount;

		// Add the LAST product to XML (after foreach ends) - with comparison check
		if ($lastProductId > 0) {
			// Determine desired product state based on total quantity
			$desiredVisibility = ($totalQuantity > 1) ? 'both' : 'search';
			$desiredActive = '1';
			$desiredAvailableForOrder = '1';
			
			// OPTIMIZATION: Only add to XML if product state needs to change
			$needsUpdate = false;
			if (isset($this->productsCache[$lastProductId])) {
				$currentState = $this->productsCache[$lastProductId];
				if ($currentState['visibility'] != $desiredVisibility || 
				    $currentState['active'] != $desiredActive || 
				    $currentState['available_for_order'] != $desiredAvailableForOrder) {
					$needsUpdate = true;
				}
			} else {
				// Product not in cache, add it to be safe
				$needsUpdate = true;
			}
			
			if ($needsUpdate) {
				$productVisibilityXML = $xmlProductSet->addChild('product');
				$productVisibilityXML->addChild('id', $lastProductId);
				$productVisibilityXML->addChild('visibility', $desiredVisibility);
				$productVisibilityXML->addChild('active', $desiredActive);
				$productVisibilityXML->addChild('available_for_order', $desiredAvailableForOrder);
				$productsAdded++;

				if ($debugFilter) {
					$oldState = isset($this->productsCache[$lastProductId]) ? 
						'vis:' . $this->productsCache[$lastProductId]['visibility'] . ',act:' . $this->productsCache[$lastProductId]['active'] : 'unknown';
					echo '<br>[DEBUG UPDATE] Final product ' . $lastProductId . ' → ✓ ADDED (old: ' . $oldState . ', new: vis:' . $desiredVisibility . ',act:' . $desiredActive . ', qty: ' . $totalQuantity . ')';
				}
			} else {
				$productsSkipped++;
				if ($debugFilter) {
					echo '<br>[DEBUG UPDATE] Final product ' . $lastProductId . ' → ⊘ SKIPPED (unchanged: vis:' . $desiredVisibility . ',act:' . $desiredActive . ', qty: ' . $totalQuantity . ')';
				}
			}
		}
		
		// Display optimization summary
		echo '<br><br>======================================';
		echo '<br><strong style="color: #0066cc;">OPTIMIZATION SUMMARY</strong>';
		echo '<br>======================================';
		echo '<br><strong>Stock Availables:</strong>';
		echo '<br>  - Added to update XML: <strong style="color: green;">' . $stocksAdded . '</strong>';
		echo '<br>  - Skipped (unchanged): <strong style="color: orange;">' . $stocksSkipped . '</strong>';
		$stockTotal = $stocksAdded + $stocksSkipped;
		if ($stockTotal > 0) {
			$stockSkipPercent = round(($stocksSkipped / $stockTotal) * 100, 1);
			echo '<br>  - Skip rate: <strong>' . $stockSkipPercent . '%</strong>';
		}
		echo '<br><br><strong>Products:</strong>';
		echo '<br>  - Added to update XML: <strong style="color: green;">' . $productsAdded . '</strong>';
		echo '<br>  - Skipped (unchanged): <strong style="color: orange;">' . $productsSkipped . '</strong>';
		$productTotal = $productsAdded + $productsSkipped;
		if ($productTotal > 0) {
			$productSkipPercent = round(($productsSkipped / $productTotal) * 100, 1);
			echo '<br>  - Skip rate: <strong>' . $productSkipPercent . '%</strong>';
		}
		echo '<br>======================================';

		if ($debugFilter) {
			echo '<br>[DEBUG UPDATE] ================================================';
			echo '<br>[DEBUG UPDATE] Files generated:';
			echo '<br>[DEBUG UPDATE] - ' . STOCK_AVAILABLES_UPDATE;
			echo '<br>[DEBUG UPDATE] - ' . PRODUCTS_UPDATE;
			echo '<br>[DEBUG UPDATE] - notFindedCombinations.xlsx';
		}
		
		$writer = new Xlsx($spreadsheet);
		$writer->save(__DIR__ . '/notFindedCombinations.xlsx');
		// Use full path by combining __DIR__ with the relative path from constants
		$stockPath = __DIR__ . '/' . STOCK_AVAILABLES_UPDATE;
		$productsPath = __DIR__ . '/' . PRODUCTS_UPDATE;
		echo '<br>[DEBUG] Saving stock XML to: ' . $stockPath;
		echo '<br>[DEBUG] Saving products XML to: ' . $productsPath;
		$xmlStockAvailables->asXML($stockPath);
		$xmlProductSet->asXML($productsPath);
	}

	/**
	 * Update stock_availables via PrestaShop 8 API using PATCH method
	 * Based on working test-single-update.php logic
	 */
	function updateStockAvailablesViaAPI() {
		$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '');

		if ($debugFilter) {
			echo '<br><br>[DEBUG API] ================================================';
			echo '<br>[DEBUG API] Starting stock_availables API update...';
		}

		$xmlStockSource = simplexml_load_file(STOCK_AVAILABLES_UPDATE, null, LIBXML_NOBLANKS);

		// Check if stock_available is array or single element
		if (isset($xmlStockSource->stock_available)) {
			$stockAvailables = $xmlStockSource->stock_available;
			// Handle single element vs array
			if (!is_array($stockAvailables) && !($stockAvailables instanceof Traversable)) {
				$stockAvailables = [$stockAvailables];
			}
		} else {
			if ($debugFilter) {
				echo '<br>[DEBUG API] No stock_availables found in XML!';
			}
			return ['success' => 0, 'errors' => 0];
		}

		$totalCount = count($stockAvailables);
		$successCount = 0;
		$errorCount = 0;
		$processedIds = []; // Track processed IDs to prevent duplicates

		if ($debugFilter) {
			echo '<br>[DEBUG API] Found ' . $totalCount . ' stock_available(s) to update...';
		}

		// PrestaShop 8 requires individual updates for each stock_available
		foreach ($stockAvailables as $stockAvailable) {
			$stockId = (int) $stockAvailable->id;
			$quantity = (int) $stockAvailable->quantity;

			// Skip if already processed (prevent infinite loop)
			if (in_array($stockId, $processedIds)) {
				if ($debugFilter) {
					echo '<br>[DEBUG API] Stock ID ' . $stockId . ' already processed, skipping...';
				}
				continue;
			}
			$processedIds[] = $stockId;

			if ($debugFilter) {
				echo '<br>[DEBUG API] Updating stock_available ID: ' . $stockId . ' → Quantity: ' . $quantity . '... ';
			}

			try {
				// Get current stock_available data from API (same as test-single-update.php)
				$currentOpt = [
					'resource' => 'stock_availables',
					'id' => $stockId,
					'id_shop' => ID_SHOP  // Add shop ID for multistore support
				];
				$currentXml = $this->api->get($currentOpt);

				// Update only the quantity field
				$currentXml->stock_available->quantity = $quantity;

				// Send PATCH without 'id' parameter (PrestaShop 8 requirement)
				$opt = [
					'resource' => 'stock_availables',
					// NOTE: No 'id' here! ID is in XML only, not in URL
					'putXml' => $currentXml->asXML(),
					'request_type' => 'PATCH',
					'id_shop' => ID_SHOP  // Add shop ID for multistore support
				];

				$xmlOutput = $this->api->edit($opt);

				if ($debugFilter) {
					echo '✓ OK';
				}
				$successCount++;

			} catch (PrestaShopWebserviceException $ex) {
				if ($debugFilter) {
					echo '✗ FAILED - ' . $ex->getMessage();
				}
				$errorCount++;
			}
		}

		if ($debugFilter) {
			echo '<br>[DEBUG API] ================================================';
			echo '<br>[DEBUG API] Summary: ' . $successCount . ' updated, ' . $errorCount . ' errors';
			echo '<br>[DEBUG API] Total IDs processed: ' . count($processedIds);
			echo '<br>[DEBUG API] ================================================';
		}

		return ['success' => $successCount, 'errors' => $errorCount];
	}

	/**
	 * Update products (visibility, active) via PrestaShop 8 API using PATCH method
	 */
	function updateProductsViaAPI() {
		$debugFilter = (isset($_GET['debug_code']) && $_GET['debug_code'] !== '');

		if ($debugFilter) {
			echo '<br><br>[DEBUG PRODUCTS] ================================================';
			echo '<br>[DEBUG PRODUCTS] Starting products API update (visibility/active)...';
		}

		$xmlProductsSource = simplexml_load_file(PRODUCTS_UPDATE, null, LIBXML_NOBLANKS);

		// Check if product is array or single element
		if (isset($xmlProductsSource->product)) {
			$products = $xmlProductsSource->product;
			// Handle single element vs array
			if (!is_array($products) && !($products instanceof Traversable)) {
				$products = [$products];
			}
		} else {
			if ($debugFilter) {
				echo '<br>[DEBUG PRODUCTS] No products found in XML!';
			}
			return ['success' => 0, 'errors' => 0];
		}

		$totalCount = count($products);
		$successCount = 0;
		$errorCount = 0;

		if ($debugFilter) {
			echo '<br>[DEBUG PRODUCTS] Found ' . $totalCount . ' product(s) to update...';
		}

		// PrestaShop 8 requires individual updates for each product
		foreach ($products as $product) {
			$productId = (int) $product->id;
			$visibility = (string) $product->visibility;
			$active = (int) $product->active;

			if ($debugFilter) {
				echo '<br>[DEBUG PRODUCTS] Updating product ID: ' . $productId . ' → Visibility: ' . $visibility . ', Active: ' . $active . '... ';
			}

			try {
				// Create a minimal XML with ONLY the fields we want to update
				// This avoids "not writable" errors for read-only fields
				$minimalXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"></prestashop>');
				$productNode = $minimalXml->addChild('product');
				$productNode->addChild('id', $productId);
				$productNode->addChild('visibility', $visibility);
				$productNode->addChild('active', $active);
				$productNode->addChild('available_for_order', '1');

				// Send PATCH without 'id' parameter (PrestaShop 8 requirement)
				$opt = [
					'resource' => 'products',
					'putXml' => $minimalXml->asXML(),
					'request_type' => 'PATCH',
					'id_shop' => ID_SHOP  // Add shop ID for multistore support
				];

				$xmlOutput = $this->api->edit($opt);

				if ($debugFilter) {
					echo '✓ OK';
				}
				$successCount++;

			} catch (PrestaShopWebserviceException $ex) {
				if ($debugFilter) {
					echo '✗ FAILED - ' . $ex->getMessage();
				}
				$errorCount++;
			}
		}

		if ($debugFilter) {
			echo '<br>[DEBUG PRODUCTS] ================================================';
			echo '<br>[DEBUG PRODUCTS] Summary: ' . $successCount . ' updated, ' . $errorCount . ' errors';
			echo '<br>[DEBUG PRODUCTS] ================================================';
		}

		return ['success' => $successCount, 'errors' => $errorCount];
	}
}
