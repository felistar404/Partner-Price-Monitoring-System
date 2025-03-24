<?php

require_once '../../../config/conn.php';
/**
 * Simple script to fetch product names and IDs from price.com.hk
 * Extracts model numbers from product names
 */

// Set maximum execution time to 0 (unlimited)
set_time_limit(0);
ini_set('max_execution_time', 0);

// Database connection parameters
// $db_host = 'localhost';
// $db_user = 'root';
// $db_pass = '';
// $db_name = 'price_monitoring_system';

// // Connect to database
// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// if ($conn->connect_error) {
//     die("Database connection failed: " . $conn->connect_error);
// }

// Check if we're canceling the process
if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
    // Create a cancel flag file
    file_put_contents(__DIR__ . "/cancel_flag.txt", date("Y-m-d H:i:s") . " - Process canceled by user");
    echo "<div style='background-color: #ffeeee; padding: 15px; border: 1px solid #cc0000; margin: 20px 0;'>";
    echo "<h2>Process Canceled</h2>";
    echo "<p>The data fetching process has been flagged for cancellation. The next request cycle will terminate.</p>";
    echo "<p><a href='?'>Return to main page</a> | <a href='?use_sample=1'>Use sample data</a></p>";
    echo "</div>";
    exit;
}

// Execution starts here
echo "<h1>Price.com.hk Product Extractor</h1>";

// Add a cancel button at the top of the page if not using sample data
if (!isset($_GET['use_sample']) || $_GET['use_sample'] != '1') {
    echo "<div style='margin-bottom: 15px;'>";
    echo "<a href='?cancel=1' style='display: inline-block; padding: 8px 15px; background-color: #cc0000; color: white; text-decoration: none; border-radius: 4px;'>Cancel Running Process</a>";
    echo " <a href='?use_sample=1' style='display: inline-block; padding: 8px 15px; background-color: #0066cc; color: white; text-decoration: none; border-radius: 4px;'>Switch to Sample Data</a>";
    echo "</div>";
}

// Path for the result file
$result_file = __DIR__ . "/result.txt";

// Check if we're using sample data
$use_sample = isset($_GET['use_sample']) && $_GET['use_sample'] == '1';

// If using sample data, load from JSON file
if ($use_sample) {
    echo "<div style='background-color: #ffd; padding: 10px; border: 1px solid #eb3; margin-bottom: 15px;'>";
    echo "<strong>Using sample data from product_page_sample.json file</strong>";
    echo " | <a href='?'>Switch to live data</a>";
    echo "</div>";
    
    $sample_file = __DIR__ . "/../temp/product_page_sample.json";
    
    if (file_exists($sample_file)) {
        $json_data = file_get_contents($sample_file);
        $products = json_decode($json_data, true);
        
        if ($products === null) {
            echo "<p style='color: red;'>Error decoding JSON data from sample file!</p>";
            exit;
        }
        
        // Apply the extract_model_from_name function to reprocess all models
        foreach ($products as &$product) {
            $product['original_model'] = $product['model']; // Save original model for comparison
            $product['model'] = extract_model_from_name($product['full_name']);
        }
        
        // Deduplicate products based on ID and filter out items without price info
        $unique_products = [];
        $removed_count = 0;
        $no_price_count = 0;
        
        foreach ($products as $product) {
            // Skip products without price information
            if (empty($product['price']) || $product['price'] === 'N/A') {
                $no_price_count++;
                continue;
            }
            
            // Use the product ID as the key to ensure uniqueness
            $unique_products[$product['id']] = $product;
        }
        
        // Convert back to indexed array
        $products = array_values($unique_products);
        
        $duplicate_count = $removed_count - $no_price_count;
        
        // Show filtering info if items were removed
        if ($removed_count > 0 || $no_price_count > 0) {
            echo "<div style='background-color: #e6f7ff; padding: 10px; border: 1px solid #4da6ff; margin-bottom: 15px;'>";
            if ($no_price_count > 0) {
                echo "<p>Removed <strong>{$no_price_count}</strong> products without price information.</p>";
            }
            if ($duplicate_count > 0) {
                echo "<p>Removed <strong>{$duplicate_count}</strong> duplicate products (keeping one instance of each product ID).</p>";
            }
            echo "</div>";
        }
        
        // Display the results
        if (!empty($products)) {
            echo "<h3>Found a total of " . count($products) . " Products in sample file:</h3>";
            
            // Database matching and insertion
            process_products_for_database($products, $conn);
            
            // Display a comparison table to show before/after model extraction
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
            echo "<tr style='background-color:#efefef;'><th>Product ID</th><th>Full Name</th><th>Original Model</th><th>New Model</th><th>Price Range</th><th>Changed</th></tr>";
            
            foreach ($products as $product) {
                $changed = $product['original_model'] !== $product['model'];
                $changed_style = $changed ? 'background-color:#ffe6e6;' : '';
                
                echo "<tr style='$changed_style'>";
                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                echo "<td>" . htmlspecialchars($product['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($product['original_model']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($product['model']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($product['price'] ?? 'N/A') . "</td>";
                echo "<td>" . ($changed ? '✓' : '') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Output as JSON for easy copy-paste
            echo "<h3>JSON Format with Updated Models:</h3>";
            echo "<pre style='background-color:#f0f0f0; padding:10px; overflow:auto;'>";
            // Remove the original_model field for the output
            $output_products = array_map(function($p) {
                unset($p['original_model']);
                return $p;
            }, $products);
            echo json_encode($output_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
        } else {
            echo "<p>No products found in the sample file.</p>";
        }
    } else {
        echo "<p style='color: red;'>Sample file not found at: " . htmlspecialchars($sample_file) . "</p>";
    }
} else {
    // Original live data fetching code
    echo "<div style='background-color: #eff; padding: 10px; border: 1px solid #3be; margin-bottom: 15px;'>";
    echo "<strong>Using live data from price.com.hk</strong>";
    echo " | <a href='?use_sample=1'>Switch to sample data</a>";
    echo "</div>";
    
    // Base URL - now using a template where we can replace the page number
    $base_url = "https://www.price.com.hk/search.php?g=A&q=QNAP&c=100031&page=%d";
    
    // Initialize counters
    $page_count = 0;
    $product_count = 0;
    $start_time = time();
    
    // Get the first page to determine total pages
    $first_page_url = sprintf($base_url, 1);
    echo "<p>Starting URL: <a href='$first_page_url' target='_blank'>$first_page_url</a></p>";
    $content = fetch_url_content($first_page_url);
    
    if (!$content) {
        log_to_file($result_file, "Initial fetch failed - possible IP ban");
        echo "<p style='color: red;'>Failed to fetch first page - possible IP ban!</p>";
        exit;
    }
    
    // Extract pagination information
    $total_pages = extract_pagination_info($content);
    if ($total_pages > 0) {
        echo "<h3>Pagination Information:</h3>";
        echo "<p>Total Pages: <strong>{$total_pages}</strong></p>";
        
        // Calculate delay between requests to spread the process over 6-8 hours
        $min_total_time = 6 * 60 * 60; // 6 hours in seconds
        $max_total_time = 8 * 60 * 60; // 8 hours in seconds
        $avg_total_time = ($min_total_time + $max_total_time) / 2; // Average time
        $base_delay = ceil($avg_total_time / $total_pages);
        
        log_to_file($result_file, "Starting fetch process for $total_pages pages with average delay of $base_delay seconds between requests");
        echo "<p>Estimated average delay between requests: {$base_delay} seconds</p>";
        
        // Process the first page results
        $products = extract_products($content);
        $product_count += count($products);
        $page_count++;
        
        log_to_file($result_file, "Processed page 1 of $total_pages, found " . count($products) . " products");
        
        // Now fetch remaining pages
        for ($page = 2; $page <= $total_pages; $page++) {
            // Check if cancellation has been requested
            if (file_exists(__DIR__ . "/cancel_flag.txt")) {
                log_to_file($result_file, "Process canceled by user after processing $page_count pages and finding $product_count products");
                echo "<p style='color: red; font-weight: bold;'>Process canceled by user. Terminating...</p>";
                @unlink(__DIR__ . "/cancel_flag.txt"); // Remove the flag file
                break;
            }
            
            // Calculate a randomized delay (±30% of base delay)
            $random_factor = mt_rand(70, 130) / 100;
            $actual_delay = round($base_delay * $random_factor);
            
            // Sleep to space out requests with random delay
            echo "<p>Waiting {$actual_delay} seconds before fetching page {$page}...</p>";
            flush();
            sleep($actual_delay);
            
            $page_url = sprintf($base_url, $page);
            echo "<p>Fetching page {$page} of {$total_pages}: <a href='$page_url' target='_blank'>$page_url</a></p>";
            $content = fetch_url_content($page_url);
            
            if ($content) {
                $page_products = extract_products($content);
                $product_count += count($page_products);
                $page_count++;
                
                log_to_file($result_file, "Processed page $page of $total_pages, found " . count($page_products) . " products");
                
                echo "<p>Found " . count($page_products) . " products on page {$page}.</p>";
                
                // Merge with existing products
                $products = array_merge($products, $page_products);
            } else {
                // If fetch fails, assume IP ban
                log_to_file($result_file, "IP possibly banned after processing $page_count pages and finding $product_count products");
                echo "<p style='color: red;'>Failed to fetch page {$page} - possible IP ban! Terminating process.</p>";
                break;
            }
        }
        
        $end_time = time();
        $total_time = $end_time - $start_time;
        $hours = floor($total_time / 3600);
        $minutes = floor(($total_time % 3600) / 60);
        $seconds = $total_time % 60;
        
        $time_string = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        
        if ($page_count == $total_pages) {
            log_to_file($result_file, "Successfully completed fetching all $page_count pages containing $product_count products in $time_string");
        } else {
            log_to_file($result_file, "Partially completed fetching $page_count of $total_pages pages containing $product_count products in $time_string");
        }
        
        // main
        if (!empty($products)) {
            // Deduplicate products based on ID and filter out items without price info
            $unique_products = [];
            $total_products = count($products);
            $no_price_count = 0;
            
            foreach ($products as $product) {
                // Skip products without price information
                if (empty($product['price']) || $product['price'] === 'N/A') {
                    $no_price_count++;
                    continue;
                }
                
                // Use the product ID as the key to ensure uniqueness
                $unique_products[$product['id']] = $product;
            }
            
            // Convert back to indexed array
            $products = array_values($unique_products);
            
            $removed_count = $total_products - count($products);
            $duplicate_count = $removed_count - $no_price_count;
            
            // Log and display filtering results
            if ($removed_count > 0) {
                $log_message = "Filtered out $removed_count items: $no_price_count without price, $duplicate_count duplicates";
                log_to_file($result_file, $log_message);
                
                echo "<div style='background-color: #e6f7ff; padding: 10px; border: 1px solid #4da6ff; margin-bottom: 15px;'>";
                echo "<h4>Data Filtering Results:</h4>";
                if ($no_price_count > 0) {
                    echo "<p>Removed <strong>{$no_price_count}</strong> products without price information.</p>";
                }
                if ($duplicate_count > 0) {
                    echo "<p>Removed <strong>{$duplicate_count}</strong> duplicate products (keeping one instance of each product ID).</p>";
                }
                echo "<p>Final product count: <strong>" . count($products) . "</strong></p>";
                echo "</div>";
            }
            
            // Database matching and insertion
            process_products_for_database($products, $conn);
            
            echo "<h3>Found a total of " . count($products) . " Products across {$page_count} pages:</h3>";
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
            echo "<tr style='background-color:#efefef;'><th>Product ID</th><th>Full Name</th><th>Model</th><th>Price Range</th></tr>";
            
            foreach ($products as $product) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                echo "<td>" . htmlspecialchars($product['full_name']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($product['model']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($product['price'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Output as JSON for easy copy-paste
            echo "<h3>JSON Format:</h3>";
            echo "<pre style='background-color:#f0f0f0; padding:10px; overflow:auto;'>";
            echo json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
        } else {
            echo "<p>No products found in the content.</p>";
        }
    } else {
        echo "<p style='color: red;'>Failed to determine total pages!</p>";
        log_to_file($result_file, "Failed to determine total pages");
    }
}

/**
 * Process products for database matching and insertion
 */
function process_products_for_database($products, $conn) {
    // Array to track matched/unmatched products
    $matched_count = 0;
    $matched_products = [];
    $unmatched_products = [];
    
    // Get all product models from the database
    $query = "SELECT product_id, product_model FROM products WHERE product_status = 'active'";
    $result = $conn->query($query);
    
    if ($result) {
        $db_products = [];
        while ($row = $result->fetch_assoc()) {
            $db_products[$row['product_model']] = $row['product_id'];
        }
        
        // Check if we have any products in the database
        if (empty($db_products)) {
            echo "<div style='background-color: #fff3cd; padding: 10px; border: 1px solid #ffeeba; margin-bottom: 15px;'>";
            echo "<strong>No active products found in the database.</strong> Please add products first.";
            echo "</div>";
            return;
        }
        
        foreach ($products as $product) {
            // Try to match the model with the database
            if (isset($db_products[$product['model']])) {
                $product_id = $db_products[$product['model']];
                $platform_id = 1; // Hardcoded as per requirements
                $platform_product_id = $product['id'];
                
                // Check if the mapping already exists
                $check_query = "SELECT mapping_id FROM product_url_mappings 
                                WHERE product_id = ? AND platform_id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("ii", $product_id, $platform_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Mapping exists, update it
                    $row = $check_result->fetch_assoc();
                    $mapping_id = $row['mapping_id'];
                    
                    $update_query = "UPDATE product_url_mappings 
                                    SET platform_product_id = ?, updated_at = CURRENT_TIMESTAMP 
                                    WHERE mapping_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("si", $platform_product_id, $mapping_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Mapping doesn't exist, insert new one
                    $insert_query = "INSERT INTO product_url_mappings 
                                    (product_id, platform_id, platform_product_id) 
                                    VALUES (?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("iis", $product_id, $platform_id, $platform_product_id);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                $check_stmt->close();
                $matched_count++;
                $matched_products[] = $product;
            } else {
                $unmatched_products[] = $product;
            }
        }
        
        // Display database matching results
        echo "<div style='background-color: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin-bottom: 15px;'>";
        echo "<h4>Database Matching Results:</h4>";
        echo "<p>Found <strong>{$matched_count}</strong> products that match models in the database.</p>";
        echo "<p>Unable to match <strong>" . count($unmatched_products) . "</strong> products with any model in the database.</p>";
        echo "</div>";
        
        // Display matched products table if any were matched
        if (!empty($matched_products)) {
            echo "<h4>Products Matched with Database:</h4>";
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; margin-bottom: 20px;'>";
            echo "<tr style='background-color:#d4edda;'><th>Platform Product ID</th><th>Product Name</th><th>Model</th><th>Price Range</th></tr>";
            
            foreach ($matched_products as $product) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                echo "<td>" . htmlspecialchars($product['full_name']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($product['model']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($product['price'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        // Display unmatched products table if any weren't matched
        if (!empty($unmatched_products)) {
            echo "<h4>Products NOT Matched with Database:</h4>";
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; margin-bottom: 20px;'>";
            echo "<tr style='background-color:#f8d7da;'><th>Platform Product ID</th><th>Product Name</th><th>Model</th><th>Price Range</th></tr>";
            
            foreach ($unmatched_products as $product) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                echo "<td>" . htmlspecialchars($product['full_name']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($product['model']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($product['price'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    } else {
        echo "<div style='background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin-bottom: 15px;'>";
        echo "<strong>Database Error:</strong> " . $conn->error;
        echo "</div>";
    }
}

/**
 * Function to log messages to a file
 */
function log_to_file($file, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($file, $log_message, FILE_APPEND);
}

/**
 * Function to extract pagination information
 */
function extract_pagination_info($html) {
    // Look for the total page count pattern "共 xx 頁"
    if (preg_match('/共\s+(\d+)\s+頁/', $html, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

/**
 * Function to extract product IDs and names from HTML content
 */
function extract_products($html) {
    $products = [];
    
    // Regular expression to find product track IDs and extract the numeric ID
    if (preg_match_all('/<li id="track_(\d+)"/', $html, $id_matches)) {
        $product_ids = $id_matches[1];
        
        // For each product ID, find the corresponding product name
        foreach ($product_ids as $index => $id) {
            // Find the product name within the li element
            $pattern = '/<li id="track_' . $id . '">.*?<a[^>]*>([^<]+)<\/a>.*?<\/li>/s';
            if (preg_match($pattern, $html, $name_match)) {
                $product_name = trim($name_match[1]);
            } else {
                // Alternative pattern looking for the product link in the HTML
                $pattern = '/<li id="track_' . $id . '".*?>.*?<a[^>]*href="[^"]*p=' . $id . '[^"]*"[^>]*>(.*?)<\/a>/s';
                if (preg_match($pattern, $html, $name_match)) {
                    $product_name = trim(strip_tags($name_match[1]));
                } else {
                    // Third attempt - more generic pattern
                    $li_pattern = '/<li id="track_' . $id . '".*?<div class="line line-01">\s*<a[^>]*>(.*?)<\/a>/s';
                    if (preg_match($li_pattern, $html, $name_match)) {
                        $product_name = trim(strip_tags($name_match[1]));
                    } else {
                        $product_name = "Unknown Product";
                    }
                }
            }
            
            // Extract the model number from the product name
            $model = extract_model_from_name($product_name);
            
            // Extract price range if available
            $price_pattern = '/<li id="track_' . $id . '".*?text-price-number[^>]*>([^<]+)<.*?text-price-number[^>]*>([^<]+)</s';
            $price_range = '';
            if (preg_match($price_pattern, $html, $price_match)) {
                $price_range = "HK$" . trim($price_match[1]) . " - HK$" . trim($price_match[2]);
            }
            
            $products[] = [
                'id' => $id,
                'full_name' => $product_name,
                'model' => $model,
                'price' => $price_range
            ];
        }
    }
    
    return $products;
}

/**
 * Function to extract the model number from a product name
 */
function extract_model_from_name($name) {
    // Clean up the input
    $cleaned_name = str_replace('_', '-', $name); // Fix case 5: Replace underscores with hyphens
    
    // Additional specific model corrections based on known patterns
    $specific_patterns = [
        // Case: HS-251+ (model with + suffix)
        '/\bHS-251\+\b/i' => 'HS-251+',
        
        // Case: TS-419P II (model with 'II' suffix)
        '/\bTS-419P\s*II\b/i' => 'TS-419P II',
        
        // Also handle similar patterns
        '/\b(TS-\d+P)\s*II\b/i' => '$1 II'
    ];
    
    // Apply specific corrections first - exact matches take precedence
    foreach ($specific_patterns as $pattern => $replacement) {
        if (preg_match($pattern, $cleaned_name, $matches)) {
            if ($pattern === '/\bHS-251\+\b/i' && preg_match('/HS-251\+/i', $cleaned_name)) {
                return 'HS-251+';
            }
            if (preg_match($pattern, $cleaned_name)) {
                return $replacement;
            }
        }
    }
    
    // Handle case: Typo in "pro" word (no space)
    $cleaned_name = preg_replace('/([A-Z0-9\-]+)pro\b/i', '$1 Pro', $cleaned_name);
    
    // Improved memory specification pattern to capture -XG and -XGB for X = {2,4,8,16,32,64}
    // Model memory specs can be -2G, -4G, -8G, etc. or -8GB, -16GB, etc.
    $memory_spec_pattern = '-(?:2|4|8|16|32|64)G(?:B)?';
    
    // Common QNAP model patterns with improved regex
    $patterns = [
        // Match TS-XXXX model format with optional memory spec
        '/\b(TS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        
        // Match TR-XXXX model format
        '/\b(TR-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        
        // Match TVS-XXXX model format
        '/\b(TVS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        
        // Match HS-XXXX model format - special handling for HS-251+
        '/\b(HS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        
        // Match TBS-XXXX model format
        '/\b(TBS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        
        // Match REXP-XXXX model format (for expansion units)
        '/\b(REXP-[A-Za-z0-9\-]+\d*(?:[A-Za-z0-9\-]*)?(?:-RP)?)\b/i',
        
        // Generic fallback pattern
        '/\b([A-Z]{2,3}-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $cleaned_name, $matches)) {
            $model = $matches[1];
            
            // Handle memory specifications like -8GB
            if (preg_match('/(.*?)(' . $memory_spec_pattern . ')$/i', $model, $mem_matches)) {
                $model_base = $mem_matches[1];
                $model_mem = $mem_matches[2];
                
                // Ensure we maintain the memory spec as is
                $model = $model_base . $model_mem;
            }
            
            // Specific handling for HS-251+ which often doesn't get captured correctly
            if ($model === 'HS-251' && strpos($cleaned_name, 'HS-251+') !== false) {
                $model = 'HS-251+';
            }
            
            // Special handling for BT3-8GB style models
            if (preg_match('/\b(TS-\d+BT\d+)(?:-(\d+GB?))?/i', $cleaned_name, $bt_matches)) {
                if (isset($bt_matches[2])) {
                    return $bt_matches[1] . '-' . $bt_matches[2];
                } else {
                    return $bt_matches[1];
                }
            }
            
            // Handle "Pro" suffix - only if it exists in the full name
            if (stripos($cleaned_name, ' Pro') !== false && stripos($model, 'Pro') === false) {
                // Only add "Pro" if it appears to belong to the model (if it's within a reasonable distance)
                $model_pos = stripos($cleaned_name, $model);
                $pro_pos = stripos($cleaned_name, 'Pro');
                
                // If "Pro" is within 10 characters after the model, it likely belongs to the model
                if ($pro_pos > $model_pos && ($pro_pos - $model_pos - strlen($model)) <= 10) {
                    // Check if it should be "Pro+" (only if "Pro+" actually exists in the full name)
                    if (strpos($cleaned_name, 'Pro+') !== false) {
                        $model .= ' Pro+';
                    } else {
                        $model .= ' Pro';
                    }
                }
            }
            
            // Handle "II" suffix for models
            if (preg_match('/\b' . preg_quote($model, '/') . '\s+II\b/i', $cleaned_name)) {
                $model .= ' II';
            }
            
            return $model;
        }
    }
    
    // Handle case 3: If the model is not found by regex but the name seems to be a model itself
    // For cases like QNAP QG-103N where the model is QG-103N
    if (preg_match('/\b([A-Z]{2,3}-[A-Za-z0-9\-]+)\b/i', $cleaned_name, $matches)) {
        return $matches[1];
    }
    
    // Special check for HS-251+ that might have been missed
    if (strpos($cleaned_name, 'HS-251+') !== false) {
        return 'HS-251+';
    }
    
    // If no model is found, return the original name
    return $name;
}

/**
 * Simple function to fetch URL content with IP ban detection
 */
function fetch_url_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_REFERER, "https://www.price.com.hk/");
    curl_setopt($ch, CURLOPT_COOKIE, "visited=1; lastVisit=" . time());
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "<p>cURL Error: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Check for potential IP ban (403 Forbidden, 429 Too Many Requests, or empty response)
    if ($http_code == 403 || $http_code == 429 || empty($response)) {
        echo "<p style='color: red;'>Possible IP ban detected! HTTP Code: $http_code</p>";
        return false;
    }
    
    return $response;
}
?>
