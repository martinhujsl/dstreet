<?php
/**
 * Show products from products_notfind.xml
 * These are products that exist in eshop but are NOT in source XML
 */

require_once('defines.cfg.php');
require_once('PSWebServiceLibrary.php');

echo '<h2>Products NOT Found in Source XML</h2>';
echo '<p>These products exist in eshop (manufacturer ID: ' . MANUFACTURER_ID . ') but are missing from source XML file.</p>';
echo '<p><strong>File:</strong> ' . PRODUCTS_NOT_FIND . '</p>';
echo '<hr>';

try {
    // Check if file exists
    if (!file_exists(PRODUCTS_NOT_FIND)) {
        echo '<p style="color: orange;"><strong>Warning:</strong> File not found: ' . PRODUCTS_NOT_FIND . '</p>';
        echo '<p>Run import first to generate this file.</p>';
        die();
    }
    
    // Load products_notfind.xml
    $xmlSource = simplexml_load_file(PRODUCTS_NOT_FIND, null, LIBXML_NOBLANKS);
    
    if (!isset($xmlSource->product) || count($xmlSource->product) == 0) {
        echo '<p style="color: green;"><strong>✓ Perfect!</strong> All products from eshop are in source XML.</p>';
        echo '<p>No missing products found.</p>';
        die();
    }
    
    $products = $xmlSource->product;
    $totalCount = count($products);
    
    echo '<p style="color: orange;"><strong>⚠ Found ' . $totalCount . ' product(s) in eshop that are NOT in source XML!</strong></p>';
    echo '<p>These products will be set to: <strong>visibility=search, active=0</strong></p>';
    echo '<hr>';
    
    // Initialize API
    $api = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, false);
    
    echo '<p>Loading product details from API...</p>';
    flush();
    
    // Collect all product IDs
    $productIds = [];
    foreach ($products as $product) {
        $productIds[] = (int)$product->id;
    }
    
    echo '<p>Found ' . count($productIds) . ' product IDs to fetch...</p>';
    flush();
    
    // Fetch all products in one call (if possible)
    $productDetails = [];
    
    // Optimal batch size: 500 products per request
    // (1000 causes HTTP 414 - URI Too Long, 500 works perfectly)
    $batchSize = 500;
    $batches = array_chunk($productIds, $batchSize);
    
    foreach ($batches as $batchIndex => $batchIds) {
        echo '<p>Loading batch ' . ($batchIndex + 1) . ' of ' . count($batches) . ' (' . count($batchIds) . ' products)...</p>';
        flush();
        
        // Fetch products with filter
        $filterIds = implode('|', $batchIds);
        $opt = [
            'resource' => 'products',
            'display' => '[id,name,reference,active]',
            'filter[id]' => '[' . $filterIds . ']'
        ];
        
        try {
            $xml = $api->get($opt);
            
            if (isset($xml->products)) {
                foreach ($xml->products->children() as $product) {
                    $id = (string)$product->id;
                    $productDetails[$id] = [
                        'id' => $id,
                        'reference' => (string)$product->reference,
                        'name' => (string)$product->name->language,
                        'active' => (string)$product->active
                    ];
                }
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">Error loading batch: ' . $e->getMessage() . '</p>';
        }
    }
    
    echo '<p style="color: green;"><strong>✓ Loaded details for ' . count($productDetails) . ' products</strong></p>';
    echo '<hr>';
    
    // Display table
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr style="background-color: #f0f0f0;">';
    echo '<th>ID</th>';
    echo '<th>Reference</th>';
    echo '<th>Name</th>';
    echo '<th>Currently Active</th>';
    echo '<th>Will be Set To</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    
    $notFoundCount = 0;
    foreach ($productIds as $productId) {
        if (isset($productDetails[$productId])) {
            $product = $productDetails[$productId];
            $currentActive = $product['active'] == '1' ? '✓ Yes' : '✗ No';
            $rowColor = $product['active'] == '1' ? '#fff3cd' : '#ffffff'; // Yellow background if currently active
            
            echo '<tr style="background-color: ' . $rowColor . ';">';
            echo '<td>' . $product['id'] . '</td>';
            echo '<td>' . $product['reference'] . '</td>';
            echo '<td>' . htmlspecialchars($product['name']) . '</td>';
            echo '<td>' . $currentActive . '</td>';
            echo '<td><strong>Visibility: search<br>Active: 0</strong></td>';
            echo '<td>';
            echo '<a href="test-products-check.php?product_id=' . $product['id'] . '" target="_blank">Check</a>';
            echo '</td>';
            echo '</tr>';
        } else {
            $notFoundCount++;
            echo '<tr style="background-color: #ffdddd;">';
            echo '<td>' . $productId . '</td>';
            echo '<td colspan="5" style="color: red;">⚠ Could not load product details from API</td>';
            echo '</tr>';
        }
    }
    
    echo '</table>';
    
    echo '<hr>';
    echo '<h3>Summary</h3>';
    echo '<ul>';
    echo '<li><strong>Total products not in source XML:</strong> ' . $totalCount . '</li>';
    echo '<li><strong>Successfully loaded details:</strong> ' . count($productDetails) . '</li>';
    if ($notFoundCount > 0) {
        echo '<li style="color: red;"><strong>Failed to load:</strong> ' . $notFoundCount . '</li>';
    }
    echo '</ul>';
    
    echo '<h3>What This Means</h3>';
    echo '<ul>';
    echo '<li>These products exist in your eshop (from manufacturer ' . MANUFACTURER_ID . ')</li>';
    echo '<li>But they are <strong>NOT present</strong> in your source XML file: <code>' . SOURCE_XML_FILE . '</code></li>';
    echo '<li>Import will set them to: <strong>visibility=search</strong> (hidden from front) and <strong>active=0</strong> (disabled)</li>';
    echo '<li>Yellow highlighted rows = currently active products that will be disabled</li>';
    echo '</ul>';
    
    echo '<h3>Possible Reasons</h3>';
    echo '<ul>';
    echo '<li>Old products that are no longer in your supplier\'s catalog</li>';
    echo '<li>Discontinued products</li>';
    echo '<li>Products that were manually added to eshop</li>';
    echo '<li>Products from old XML files that are no longer supplied</li>';
    echo '</ul>';
    
    echo '<h3>Actions</h3>';
    echo '<ul>';
    echo '<li>If these products should stay active, add them to your source XML</li>';
    echo '<li>If they should be hidden, the import will handle it automatically</li>';
    echo '<li>Click "Check" to see detailed info about each product</li>';
    echo '</ul>';
    
} catch (Exception $e) {
    echo '<p style="color: red;"><strong>Error:</strong> ' . $e->getMessage() . '</p>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

echo '<hr>';
echo '<p><small>Generated at: ' . date('H:i:s') . '</small></p>';
echo '<p><a href="find-product-ids.php">← Back to Product List</a> | <a href="import-dstreets.php">Run Import</a></p>';
