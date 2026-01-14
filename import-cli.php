#!/usr/bin/env php
<?php
/**
 * CLI version of import-dstreets.php
 * 
 * Usage:
 *   php import-cli.php                     # Full import with API updates
 *   php import-cli.php --dry-run           # Dry run (no API updates)
 *   php import-cli.php --filter=by1327     # Filter single product by code
 *   php import-cli.php --help              # Show help
 * 
 * Windows: php import-cli.php --filter=by1327
 * Linux:   php import-cli.php --filter=by1327
 */

// Parse command line options
$options = getopt('h', ['help', 'filter:', 'dry-run']);

// Show help
if (isset($options['h']) || isset($options['help'])) {
    showHelp();
    exit(0);
}

// Check if we're in CLI mode
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line!\n");
}

// Set debug code filter from command line
if (isset($options['filter'])) {
    $_GET['debug_code'] = $options['filter'];
    echo "==> Filter mode: Processing only products with code '{$options['filter']}'\n\n";
}

$dryRun = isset($options['dry-run']);
if ($dryRun) {
    echo "==> DRY RUN mode: No API updates will be performed (read-only)\n\n";
}

// For CLI mode, use output handler to convert HTML to plain text in real-time
ob_start(function($buffer) {
    // Convert HTML to CLI format
    $buffer = str_replace(['<br>', '<br/>', '<br />'], "\n", $buffer);
    $buffer = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '$1', $buffer);
    $buffer = preg_replace('/<[^>]+>/', '', $buffer); // Remove all other HTML tags
    return $buffer;
}, 1); // Buffer size = 1 byte for real-time output

// Start timing
$scriptStartTime = microtime(true);
echo "======================================\n";
echo "DSTREET IMPORT - CLI VERSION\n";
echo "======================================\n";
echo "Script started at: " . date('H:i:s') . "\n\n";

require_once('prestashopImportClass.php');
require __DIR__ . '/vendor/autoload.php';

try {
    $prestashopImporter = new PrestaShopWebsSrvicesImportClass();
    
    echo "Step 1: Loading products from eshop (manufacturer ID: " . MANUFACTURER_ID . ")...\n";
    flush(); // Force output
    $prestashopImporter->getProductReferencesFromEshopByManufacturer(MANUFACTURER_ID);
    echo "✓ Products loaded from eshop\n\n";
    flush();
    
    echo "Step 2: Loading stock availables from eshop...\n";
    flush();
    $prestashopImporter->getStockAvailableIds();
    echo "✓ Stock availables loaded\n\n";
    flush();
    
    echo "Step 3: Processing XML file: " . SOURCE_XML_FILE . "\n";
    echo "(This may take several minutes for large files...)\n";
    flush();
    $prestashopImporter->xmlToArray(SOURCE_XML_FILE);
    echo "✓ XML file processed\n\n";
    flush();
    
    echo "Step 4: Generating update XML files...\n";
    $prestashopImporter->createCombinationsAndProductsXmlUpdateApiFiles();
    echo "✓ Update XML files generated\n\n";
    
    echo "Step 5: Creating 'not found' products XML...\n";
    $prestashopImporter->createNotFindProductXML();
    echo "✓ Not found XML created\n\n";
    
    if ($dryRun) {
        echo "======================================\n";
        echo "DRY RUN - Skipping API updates\n";
        echo "======================================\n";
        echo "Generated files:\n";
        echo "  - " . STOCK_AVAILABLES_UPDATE . "\n";
        echo "  - " . PRODUCTS_UPDATE . "\n";
        echo "  - " . PRODUCTS_NOT_FIND . "\n";
        echo "  - notFindedCombinations.xlsx\n\n";
    } else {
        echo "Step 6: Updating stock via API...\n";
        $stockResult = $prestashopImporter->updateStockAvailablesViaAPI();
        echo "✓ Stock update completed\n\n";
        
        echo "Step 7: Updating products via API...\n";
        $productsResult = $prestashopImporter->updateProductsViaAPI();
        echo "✓ Products update completed\n\n";
        
        echo "======================================\n";
        echo "API UPDATE RESULTS\n";
        echo "======================================\n";
        echo "Stock Availables:\n";
        echo "  - Success: " . $stockResult['success'] . "\n";
        echo "  - Errors:  " . $stockResult['errors'] . "\n\n";
        echo "Products:\n";
        echo "  - Success: " . $productsResult['success'] . "\n";
        echo "  - Errors:  " . $productsResult['errors'] . "\n";
        echo "======================================\n\n";
    }
    
    // Calculate total time
    $scriptEndTime = microtime(true);
    $totalTime = $scriptEndTime - $scriptStartTime;
    
    echo "======================================\n";
    echo "EXECUTION TIME\n";
    echo "======================================\n";
    echo "Total script execution time: " . number_format($totalTime, 2) . " seconds\n";
    echo "Script finished at: " . date('H:i:s') . "\n";
    echo "======================================\n";
    
    exit(0); // Success
    
} catch (Exception $e) {
    echo "\n";
    echo "======================================\n";
    echo "ERROR\n";
    echo "======================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "======================================\n";
    exit(1); // Error
}

function showHelp() {
    echo <<<HELP
DSTREET IMPORT - CLI VERSION
=============================

USAGE:
  php import-cli.php [OPTIONS]

OPTIONS:
  --help, -h           Show this help message
  --filter=CODE        Filter by product code (e.g., --filter=by1327)
  --dry-run            Generate XML files but don't update API (read-only mode)

EXAMPLES:
  # Full import with API updates (normal mode)
  php import-cli.php

  # Dry run - generate XML but don't update API (debug mode)
  php import-cli.php --dry-run

  # Filter single product + dry run
  php import-cli.php --filter=by1327 --dry-run

  # Filter single product + update API
  php import-cli.php --filter=by1327

WINDOWS:
  php import-cli.php
  php import-cli.php --dry-run
  php import-cli.php --filter=by1327

LINUX:
  php import-cli.php
  php import-cli.php --dry-run
  php import-cli.php --filter=by1327

CRON (Linux):
  # Run every day at 3 AM
  0 3 * * * cd /path/to/dstreet && php import-cli.php > /var/log/dstreet-import.log 2>&1

TASK SCHEDULER (Windows):
  # Program: C:\path\to\php.exe
  # Arguments: Z:\work\2025\marity.ck\dstreet\import-cli.php
  # Start in: Z:\work\2025\marity.ck\dstreet

EXIT CODES:
  0 - Success
  1 - Error

HELP;
}
