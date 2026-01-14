<?php
/**
 * Find valid product IDs from manufacturer
 */

require_once('defines.cfg.php');
require_once('PSWebServiceLibrary.php');

echo '<h2>Finding Valid Product IDs</h2>';
echo '<p>Looking for products from manufacturer ID: ' . MANUFACTURER_ID . '</p>';
echo '<hr>';

try {
    $api = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, false);
    
    // First, get total count - load ALL IDs only (lightweight)
    echo '<p>Step 1: Getting total count...</p>';
    flush();
    
    $countOpt = [
        'resource' => 'products',
        'display' => '[id]',
        'filter[id_manufacturer]' => MANUFACTURER_ID
        // No limit = get all
    ];
    
    $countXml = $api->get($countOpt);
    $totalCount = 0;
    if (isset($countXml->products)) {
        $totalCount = count($countXml->products->children());
    }
    
    echo '<p style="color: green;"><strong>✓ Total products from manufacturer ' . MANUFACTURER_ID . ': ' . $totalCount . '</strong></p>';
    echo '<hr>';
    
    // Get first 50 products
    echo '<p>Step 2: Loading first 50 products...</p>';
    flush();
    
    $opt = [
        'resource' => 'products',
        'display' => '[id,name,reference,active]',
        'filter[id_manufacturer]' => MANUFACTURER_ID,
        'limit' => 50
    ];
    
    $xml = $api->get($opt);
    
    $productCount = count($xml->products->children());
    echo '<p style="color: green;"><strong>✓ Loaded ' . $productCount . ' products!</strong></p>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>ID</th><th>Reference</th><th>Name</th><th>Active</th><th>Test Link</th></tr>';
    
    foreach ($xml->products->children() as $product) {
        $id = (string)$product->id;
        $reference = (string)$product->reference;
        $name = (string)$product->name->language;
        $active = (string)$product->active;
        
        echo '<tr>';
        echo '<td>' . $id . '</td>';
        echo '<td>' . $reference . '</td>';
        echo '<td>' . $name . '</td>';
        echo '<td>' . ($active == '1' ? '✓' : '✗') . '</td>';
        echo '<td><a href="test-products-check.php?product_id=' . $id . '" target="_blank">Test</a></td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    echo '<p><strong>Click "Test" link to check any product.</strong></p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">Error: ' . $e->getMessage() . '</p>';
}

echo '<hr>';
echo '<p><small>Completed at: ' . date('H:i:s') . '</small></p>';
