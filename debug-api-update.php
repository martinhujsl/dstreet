<?php
/**
 * Debug script to check why API update is not working in main import
 */

require_once 'iniSets.cfg.php';
require_once 'defines.cfg.php';
require_once('PSWebServiceLibrary.php');

echo "<h2>Debug: API Update Investigation</h2>";

// Check if XML file exists
echo "<h3>1. Checking XML file</h3>";
$xmlPath = STOCK_AVAILABLES_UPDATE;
echo "XML Path: <code>{$xmlPath}</code><br>";

if (!file_exists($xmlPath)) {
    echo "<strong style='color:red;'>✗ XML file does NOT exist!</strong><br>";
    echo "Full path: " . realpath(dirname($xmlPath)) . "<br>";
    die("Cannot continue without XML file.");
} else {
    echo "<strong style='color:green;'>✓ XML file exists</strong><br>";
}

// Load and show XML content
echo "<h3>2. XML File Content</h3>";
$xmlStockSource = simplexml_load_file($xmlPath, null, LIBXML_NOBLANKS);
echo "<pre>" . htmlspecialchars($xmlStockSource->asXML()) . "</pre>";

$stockAvailables = $xmlStockSource->stock_available;
$totalCount = count($stockAvailables);
echo "Total stock_availables in XML: <strong>{$totalCount}</strong><br>";

if ($totalCount == 0) {
    echo "<strong style='color:red;'>✗ No stock_availables found in XML!</strong><br>";
    die("XML is empty - cannot update.");
}

// Check API connection
echo "<h3>3. Testing API Connection</h3>";
try {
    $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, true);

    // Try to get one stock_available to test connection
    $firstStock = $stockAvailables[0];
    $testId = (int) $firstStock->id;

    echo "Testing API with stock_available ID: {$testId}<br>";

    $testOpt = [
        'resource' => 'stock_availables',
        'id' => $testId
    ];

    $testResult = $webService->get($testOpt);
    echo "<strong style='color:green;'>✓ API Connection works!</strong><br>";
    echo "Current quantity in PrestaShop: <strong>" . $testResult->stock_available->quantity . "</strong><br>";

} catch (Exception $ex) {
    echo "<strong style='color:red;'>✗ API Connection FAILED!</strong><br>";
    echo "Error: " . $ex->getMessage() . "<br>";
    die("Cannot continue without API access.");
}

// Now try the actual update
echo "<h3>4. Attempting Update (same as test-single-update.php)</h3>";

foreach ($stockAvailables as $stockAvailable) {
    $stockId = (int) $stockAvailable->id;
    $quantity = (int) $stockAvailable->quantity;

    echo "<br>Processing stock_available ID: {$stockId} → Quantity: {$quantity}<br>";

    try {
        // Step 1: GET current data
        echo "&nbsp;&nbsp;Step 1: Getting current data... ";
        $currentOpt = [
            'resource' => 'stock_availables',
            'id' => $stockId
        ];
        $currentXml = $webService->get($currentOpt);
        echo "✓ (current qty: " . $currentXml->stock_available->quantity . ")<br>";

        // Step 2: Update quantity
        echo "&nbsp;&nbsp;Step 2: Updating quantity field... ";
        $currentXml->stock_available->quantity = $quantity;
        echo "✓<br>";

        // Step 3: Send PATCH
        echo "&nbsp;&nbsp;Step 3: Sending PATCH request... ";
        $opt = [
            'resource' => 'stock_availables',
            // NO 'id' parameter here!
            'putXml' => $currentXml->asXML(),
            'request_type' => 'PATCH'
        ];

        $xmlOutput = $webService->edit($opt);
        echo "<strong style='color:green;'>✓ SUCCESS</strong><br>";

        // Step 4: Verify
        echo "&nbsp;&nbsp;Step 4: Verifying update... ";
        $verifyXml = $webService->get($currentOpt);
        $newQty = (int) $verifyXml->stock_available->quantity;

        if ($newQty == $quantity) {
            echo "<strong style='color:green;'>✓ VERIFIED (qty is now {$newQty})</strong><br>";
        } else {
            echo "<strong style='color:red;'>✗ FAILED (qty is {$newQty}, expected {$quantity})</strong><br>";
        }

    } catch (Exception $ex) {
        echo "<strong style='color:red;'>✗ ERROR: " . $ex->getMessage() . "</strong><br>";
    }
}

echo "<h3>5. Conclusion</h3>";
echo "If all steps above show ✓, then the update works correctly.<br>";
echo "If you see ✗, check the specific error message above.<br>";
