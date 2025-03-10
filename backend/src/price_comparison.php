<?php
require_once '../config/conn.php';

/**
 * Compare merchant prices with reference price and categorize them
 * @param array $merchants Array of merchant data with prices
 * @param array $product Product data with reference price and acceptable range
 * @param int $platform_id Platform ID
 * @return array Categorized results and statistics
 */
function compare_prices(&$merchants, $product) {
    $result = [
        'overpriced' => [],
        'underpriced' => [],
        'acceptable' => [],
        'missing' => [],
        'stats' => [
            'total' => count($merchants),
            'overpriced_count' => 0,
            'underpriced_count' => 0,
            'acceptable_count' => 0,
            'missing_count' => 0
        ]
    ];
    
    // pass $merchant by reference to update the price status
    foreach ($merchants as &$merchant) {
        if (!isset($merchant['price']) || empty($merchant['price'])) {
            $merchant['price_status'] = 'missing';
            $result['missing'][] = $merchant;
            $result['stats']['missing_count']++;
            continue;
        }
        
        $price = floatval($merchant['price']);
        $min_acceptable = floatval($product['min_acceptable_price']);
        $max_acceptable = floatval($product['max_acceptable_price']);
        
        if ($price < $min_acceptable) {
            $merchant['price_status'] = 'underpriced';
            $result['underpriced'][] = $merchant;
            $result['stats']['underpriced_count']++;
        } elseif ($price > $max_acceptable) {
            $merchant['price_status'] = 'overpriced';
            $result['overpriced'][] = $merchant;
            $result['stats']['overpriced_count']++;
        } else {
            $merchant['price_status'] = 'acceptable';
            $result['acceptable'][] = $merchant;
            $result['stats']['acceptable_count']++;
        }
    }
    
    return $result;
}

/**
 * Store price records in the database
 * @param array $merchants Array of merchant data
 * @param array $product Product info
 * @param int $platform_id Platform ID
 * @param string $reference_key Reference key for batch
 * @return bool Success status
 */
function store_price_records($merchants, $product, $platform_id, $reference_key) {
    global $conn;
    
    $success = true;
    $product_id = $product['product_id'];
    $unmapped_count = 0;
    
    foreach ($merchants as $merchant) {
        if (!isset($merchant['shopID'])) continue;
        
        $exteral_merchant_id = $merchant['shopID'];

        $mapping_query = "SELECT merchant_id FROM platform_merchant_mappings WHERE platform_id = ? AND platform_merchant_id = ?";
        $map_stmt = $conn->prepare($mapping_query);
        if (!$map_stmt) {
            exit("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $map_stmt->bind_param("is", $platform_id, $exteral_merchant_id);
        $map_stmt->execute();
        $map_result = $map_stmt->get_result();

        if ($map_result->num_rows > 0) {
            // Found a mapping - use the real merchant ID
            $row = $map_result->fetch_assoc();
            $merchant_id = $row['merchant_id'];
            
            // Insert with the mapped merchant ID
            $price = isset($merchant['price']) ? $merchant['price'] : null;
            $price_status = isset($merchant['price_status']) ? $merchant['price_status'] : 'missing';
            
            $insert_query = "INSERT INTO price_records 
                          (product_id, merchant_id, platform_id, price, price_status, reference_key) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiidss", $product_id, $merchant_id, $platform_id, $price, $price_status, $reference_key);
            
            if (!$insert_stmt->execute()) {
                echo "Error inserting price record: " . $insert_stmt->error;
                $success = false;
            }
            $insert_stmt->close();
        } else {
            // No mapping found - log for future mapping
            $unmapped_count++;
            // echo "<div class='alert alert-warning'>Unmapped merchant: " . 
            //     htmlspecialchars($merchant['shopName']) . " (ID: " . 
            //     htmlspecialchars($external_merchant_id) . ") on platform " . $platform_id . "</div>";
                
            // Could also insert into a separate unmapped_merchants table for later processing
            continue;
        }
        $map_stmt->close();
    }
    return $success;
}
?>