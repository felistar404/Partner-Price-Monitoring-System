<?php
/**
 * Simple script to fetch product names and IDs from price.com.hk
 * Extracts model numbers from product names
 */

// Set maximum execution time to 0 (unlimited)
set_time_limit(0);
ini_set('max_execution_time', 0);

// Base URL - now using a template where we can replace the page number
$base_url = "https://www.price.com.hk/search.php?g=A&q=QNAP&c=100031&page=%d";

// Execution starts here
echo "<h1>Price.com.hk Product Extractor</h1>";

// Path for the result file
$result_file = __DIR__ . "/result.txt";

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
    
    //
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
    // Common QNAP model patterns
    $patterns = [
        // Match TS-XXXX model format (most common)
        '/\b(TS-[A-Za-z0-9\-]+\d+[A-Za-z0-9\-]*)\b/',
        
        // Match TR-XXXX model format
        '/\b(TR-[A-Za-z0-9\-]+\d+[A-Za-z0-9\-]*)\b/',
        
        // Match TVS-XXXX model format 
        '/\b(TVS-[A-Za-z0-9\-]+\d+[A-Za-z0-9\-]*)\b/',
        
        // Match HS-XXXX model format
        '/\b(HS-[A-Za-z0-9\-]+\d+[A-Za-z0-9\-]*)\b/',
        
        // Generic fallback pattern
        '/\b([A-Z]{2,3}-[A-Za-z0-9\-]+\d+[A-Za-z0-9\-]*)\b/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name, $matches)) {
            return $matches[1];
        }
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
