<?php

require_once '../../../config/conn.php';

set_time_limit(0);
ini_set('max_execution_time', 0);

if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
    file_put_contents(__DIR__ . "/cancel_flag.txt", date("Y-m-d H:i:s") . " - Process canceled by user");
    exit;
}

$result_file = __DIR__ . "/../temp/result.txt";
$use_sample = isset($_GET['use_sample']) && $_GET['use_sample'] == '1';

if ($use_sample) {
    $sample_file = __DIR__ . "/../temp/product_page_sample.json";
    
    if (file_exists($sample_file)) {
        $json_data = file_get_contents($sample_file);
        $products = json_decode($json_data, true);
        
        if ($products === null) {
            exit;
        }
        
        foreach ($products as &$product) {
            $product['original_model'] = $product['model']; 
            $product['model'] = extract_model_from_name($product['full_name']);
        }
        
        $unique_products = [];
        $removed_count = 0;
        $no_price_count = 0;
        
        foreach ($products as $product) {
            if (empty($product['price']) || $product['price'] === 'N/A') {
                $no_price_count++;
                continue;
            }
            
            $unique_products[$product['id']] = $product;
        }
        
        $products = array_values($unique_products);
        
        process_products_for_database($products, $conn);
    }
} else {
    $base_url = "https://www.price.com.hk/search.php?g=A&q=QNAP&c=100031&page=%d";
    
    $page_count = 0;
    $product_count = 0;
    $start_time = time();
    
    $first_page_url = sprintf($base_url, 1);
    $content = fetch_url_content($first_page_url);
    
    if (!$content) {
        log_to_file($result_file, "Initial fetch failed - possible IP ban");
        exit;
    }
    
    $total_pages = extract_pagination_info($content);
    if ($total_pages > 0) {
        log_to_file($result_file, "Starting fetch process for $total_pages pages with average delay between requests");
        
        $products = extract_products($content);
        $product_count += count($products);
        $page_count++;
        
        log_to_file($result_file, "Processed page 1 of $total_pages, found " . count($products) . " products");
        
        for ($page = 2; $page <= $total_pages; $page++) {
            if (file_exists(__DIR__ . "/cancel_flag.txt")) {
                log_to_file($result_file, "Process canceled by user after processing $page_count pages and finding $product_count products");
                @unlink(__DIR__ . "/cancel_flag.txt");
                break;
            }
            
            $random_factor = mt_rand(70, 130) / 100;
            $base_delay = 30;
            $actual_delay = round($base_delay * $random_factor);
            
            sleep($actual_delay);
            
            $page_url = sprintf($base_url, $page);
            $content = fetch_url_content($page_url);
            
            if ($content) {
                $page_products = extract_products($content);
                $product_count += count($page_products);
                $page_count++;
                
                log_to_file($result_file, "Processed page $page of $total_pages, found " . count($page_products) . " products");
                
                $products = array_merge($products, $page_products);
            } else {
                log_to_file($result_file, "IP possibly banned after processing $page_count pages and finding $product_count products");
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
        
        if (!empty($products)) {
            $unique_products = [];
            $total_products = count($products);
            $no_price_count = 0;
            
            foreach ($products as $product) {
                if (empty($product['price']) || $product['price'] === 'N/A') {
                    $no_price_count++;
                    continue;
                }
                
                $unique_products[$product['id']] = $product;
            }
            
            $products = array_values($unique_products);
            
            $removed_count = $total_products - count($products);
            $duplicate_count = $removed_count - $no_price_count;
            
            if ($removed_count > 0) {
                $log_message = "Filtered out $removed_count items: $no_price_count without price, $duplicate_count duplicates";
                log_to_file($result_file, $log_message);
            }
            
            process_products_for_database($products, $conn);
        }
    } else {
        log_to_file($result_file, "Failed to determine total pages");
    }
}

function process_products_for_database($products, $conn) {
    $matched_count = 0;
    $matched_products = [];
    $unmatched_products = [];
    
    $query = "SELECT product_id, product_model FROM products WHERE product_status = 'active'";
    $result = $conn->query($query);
    
    if ($result) {
        $db_products = [];
        while ($row = $result->fetch_assoc()) {
            $db_products[$row['product_model']] = $row['product_id'];
        }
        
        if (empty($db_products)) {
            return;
        }
        
        foreach ($products as $product) {
            if (isset($db_products[$product['model']])) {
                $product_id = $db_products[$product['model']];
                $platform_id = 1;
                $platform_product_id = $product['id'];
                
                $check_query = "SELECT mapping_id FROM product_url_mappings 
                                WHERE product_id = ? AND platform_id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("ii", $product_id, $platform_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
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
    }
}

function log_to_file($file, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($file, $log_message, FILE_APPEND);
}

function extract_pagination_info($html) {
    if (preg_match('/共\s+(\d+)\s+頁/', $html, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

function extract_products($html) {
    $products = [];
    
    if (preg_match_all('/<li id="track_(\d+)"/', $html, $id_matches)) {
        $product_ids = $id_matches[1];
        
        foreach ($product_ids as $index => $id) {
            $pattern = '/<li id="track_' . $id . '">.*?<a[^>]*>([^<]+)<\/a>.*?<\/li>/s';
            if (preg_match($pattern, $html, $name_match)) {
                $product_name = trim($name_match[1]);
            } else {
                $pattern = '/<li id="track_' . $id . '".*?>.*?<a[^>]*href="[^"]*p=' . $id . '[^"]*"[^>]*>(.*?)<\/a>/s';
                if (preg_match($pattern, $html, $name_match)) {
                    $product_name = trim(strip_tags($name_match[1]));
                } else {
                    $li_pattern = '/<li id="track_' . $id . '".*?<div class="line line-01">\s*<a[^>]*>(.*?)<\/a>/s';
                    if (preg_match($li_pattern, $html, $name_match)) {
                        $product_name = trim(strip_tags($name_match[1]));
                    } else {
                        $product_name = "Unknown Product";
                    }
                }
            }
            
            $model = extract_model_from_name($product_name);
            
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

function extract_model_from_name($name) {
    $cleaned_name = str_replace('_', '-', $name);
    
    $specific_patterns = [
        '/\bHS-251\+\b/i' => 'HS-251+',
        '/\bTS-419P\s*II\b/i' => 'TS-419P II',
        '/\b(TS-\d+P)\s*II\b/i' => '$1 II'
    ];
    
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
    
    $cleaned_name = preg_replace('/([A-Z0-9\-]+)pro\b/i', '$1 Pro', $cleaned_name);
    
    $memory_spec_pattern = '-(?:2|4|8|16|32|64)G(?:B)?';
    
    $patterns = [
        '/\b(TS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        '/\b(TR-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        '/\b(TVS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        '/\b(HS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        '/\b(TBS-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i',
        '/\b(REXP-[A-Za-z0-9\-]+\d*(?:[A-Za-z0-9\-]*)?(?:-RP)?)\b/i',
        '/\b([A-Z]{2,3}-[A-Za-z0-9\-]+\d+(?:[A-Za-z0-9\-]*)?(?:' . $memory_spec_pattern . ')?)\b/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $cleaned_name, $matches)) {
            $model = $matches[1];
            
            if (preg_match('/(.*?)(' . $memory_spec_pattern . ')$/i', $model, $mem_matches)) {
                $model_base = $mem_matches[1];
                $model_mem = $mem_matches[2];
                
                $model = $model_base . $model_mem; // FIXED: Using correct string concatenation
            }
            
            if ($model === 'HS-251' && strpos($cleaned_name, 'HS-251+') !== false) {
                $model = 'HS-251+';
            }
            
            if (preg_match('/\b(TS-\d+BT\d+)(?:-(\d+GB?))?/i', $cleaned_name, $bt_matches)) {
                if (isset($bt_matches[2])) {
                    return $bt_matches[1] . '-' . $bt_matches[2];
                } else {
                    return $bt_matches[1];
                }
            }
            
            if (stripos($cleaned_name, ' Pro') !== false && stripos($model, 'Pro') === false) {
                $model_pos = stripos($cleaned_name, $model);
                $pro_pos = stripos($cleaned_name, 'Pro');
                
                if ($pro_pos > $model_pos && ($pro_pos - $model_pos - strlen($model)) <= 10) {
                    if (strpos($cleaned_name, 'Pro+') !== false) {
                        $model .= ' Pro+';
                    } else {
                        $model .= ' Pro';
                    }
                }
            }
            
            if (preg_match('/\b' . preg_quote($model, '/') . '\s+II\b/i', $cleaned_name)) {
                $model .= ' II';
            }
            
            return $model;
        }
    }
    
    if (preg_match('/\b([A-Z]{2,3}-[A-Za-z0-9\-]+)\b/i', $cleaned_name, $matches)) {
        return $matches[1];
    }
    
    if (strpos($cleaned_name, 'HS-251+') !== false) {
        return 'HS-251+';
    }
    
    return $name;
}

// Price.com.hk 
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
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code == 403 || $http_code == 429 || empty($response)) {
        return false;
    }
    
    return $response;
}
?>
