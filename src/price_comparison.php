<?php
require_once '../config/conn.php';

/**
 * Compare merchant prices with reference price and categorize them
 * @param array $merchants Array of merchant data with prices
 * @param array $product Product data with reference price and acceptable range
 * @param int $platform_id Platform ID
 * @return array Categorized results and statistics
 */
function compare_prices($merchants, $product, $platform_id) {
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
    
    foreach ($merchants as $merchant) {
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
    
    foreach ($merchants as $merchant) {
        if (!isset($merchant['shopID'])) continue;
        
        $merchant_id = $merchant['shopID'];
        $price = isset($merchant['price']) ? $merchant['price'] : null;
        $price_status = isset($merchant['price_status']) ? $merchant['price_status'] : 'missing';
        
        $query = "INSERT INTO price_records 
                 (product_id, merchant_id, platform_id, parent_id, price, currency, price_status, reference_key) 
                 VALUES (?, ?, ?, 0, ?, 'HKD', ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiidss", $product_id, $merchant_id, $platform_id, $price, $price_status, $reference_key);
        
        if (!$stmt->execute()) {
            error_log("Error storing price record: " . $stmt->error);
            $success = false;
        }
        $stmt->close();
    }
    
    return $success;
}
?>