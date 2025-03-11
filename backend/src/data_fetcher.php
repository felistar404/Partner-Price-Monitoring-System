<?php

require_once '../config/conn.php';
require_once '../config/logger.php';
require_once '../lib/simple_html_dom.php';
require_once 'price_comparison.php';

// Init
$reference_key = bin2hex(random_bytes(16));

$product_query = "SELECT product_id, product_name, product_model, reference_price, 
                    min_acceptable_price, max_acceptable_price, product_description 
                    FROM products 
                    WHERE product_status = 'active'";
$stmt = $conn->prepare($product_query);
if (!$stmt) {
    exit("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->execute();
$product_result = $stmt->get_result();
$p = $product_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If more than 0, meaning there are products waiting for monitoring.
if ($product_result->num_rows === 0) {
    exit(Logger::error("No products are available for monitoring."));
}

// Main iteration
foreach ($p as $product) {
    // echo "|TEST| Current Processing product: {$product['product_name']} --> ID: {$product['product_id']}<br>";
    
    // Using $product['product_id'] to check existing sets of platform_id & platform_product_id (白話就是拿商品ID尋找它們屬於那些平台以及它對那些平台的ID是甚麼)
    $mapping_query = "SELECT pum.platform_id, pum.platform_product_id,
                    p.platform_name, p.platform_url, p.platform_url_price 
                    FROM product_url_mappings pum 
                    JOIN platforms p ON pum.platform_id = p.platform_id 
                    WHERE pum.product_id = ? AND p.platform_status = 'active'";
    $stmt = $conn->prepare($mapping_query);
    if (!$stmt) {
        exit("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $product['product_id']);
    $stmt->execute();
    $product_mapping_result = $stmt->get_result();
    $mapping_stmt = $product_mapping_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($mapping_stmt)) {
        Logger::error("The product: {$product['product_name']} (ID: {$product['product_id']}) does not belong to any platform in db record.");
    }

    foreach ($mapping_stmt as $platform_info) {

        /* complete url formed in: prefix_url + suffix_url + product_url_id
        * E.g. https://www.price.com.hk/product.php?p=633588
        * prefix_url = https://www.price.com.hk/
        * suffix_url = product.php?p=
        * product_url_id = 633588
        */

        $platform_name = $platform_info['platform_name'];
        $prefix_url = $platform_info['platform_url'];
        $suffix_url = $platform_info['platform_url_price'];
        $p_id = $platform_info['platform_product_id'];
        $url = $prefix_url . $suffix_url . $p_id;

        // echo "|TEST| Checking product on {$platform_name}: {$url}<br>";

        // Uncomment to actually process the URL
        retrieve_and_display($url, $product, $platform_info);
        // perform comparison and alert system. (internal)
        // call function to record crawl_log. (extra function)

        // echo "--------------------------------------------------------------------------<br>";

        //test
        // exit("terminates");

        // mimic human behavior
        delay_patterns();

    }
}

// final steps: stored data in db & set cooldown time
$insert_query = "INSERT INTO main_records (reference_key, update_date) VALUES (?, NOW())";
$stmt = $conn->prepare($insert_query);
if (!$stmt) {
    exit("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("s", $reference_key);
$stmt->execute();
$stmt->close();

$cooldown_query = "INSERT INTO refresh_cooldowns (reference_key, last_refresh_time, next_available_time) VALUES (?, NOW(), NOW() + INTERVAL 24 HOUR)";
$t_stmt = $conn->prepare($cooldown_query);
if (!$stmt) {
    exit("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$t_stmt->bind_param("s", $reference_key);
$t_stmt->execute();
$t_stmt->close();


function delay_patterns($context = 'default') {
    $delays = [
        'default' => [5000000, 10000000],       // 5-10 seconds
        'between_products' => [12000000, 17000000], // 12-17 seconds
        'between_pages' => [12000000, 17000000]    // 12-17 seconds
    ];
    
    $range = $delays[$context] ?? $delays['default'];
    
    $base = rand($range[0], $range[1]);
    
    // fluctuation (±10%)
    $fluctuation = rand(-$base/10, $base/10);
    
    $total = $base + $fluctuation;
    usleep($total);
    
    if ($total > 30000000) {
        echo ".";
        flush();
    }
}

function retrieve_and_display($base_url, $product, $platform_info) {
    global $reference_key;
    $all_merchants = array();
    global $conn;
    $html = fetch_url_content($base_url);

    if ($html) {
        // process the first page
        $total_pages = retrieve_total_pages($html);
        // echo "<h3>Total pages: $total_pages</h3>";
        
        // process first page results
        $page_results = retrieve_merchant_price_id($html);
        $all_merchants = array_merge($all_merchants, $page_results);
        
        // process of remaining pages
        for ($page = 2; $page <= $total_pages; $page++) {
            $page_url = $base_url . "&page=" . $page;
            // echo "<p>Fetching page $page: $page_url</p>";
            delay_patterns();
            // tested
            $page_html = fetch_url_content($page_url);
            if ($page_html) {
                $page_results = retrieve_merchant_price_id($page_html);
                $all_merchants = array_merge($all_merchants, $page_results);
            } else {
                exit(Logger::error("Failed to fetch page $page"));
            }
        } 

        // perform price records
        $comparison_results = compare_prices($all_merchants, $product);
        
        // Store price records in the database
        $platform_id = $platform_info['platform_id'];
        $storage_success = store_price_records($all_merchants, $product, $platform_id, $reference_key);
        
        // Display store status for debugging
        // if ($storage_success) {
        //     echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>
        //           <strong>Success:</strong> Price records successfully stored in database with reference key: $reference_key
        //           </div>";
        // } else {
        //     echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>
        //           <strong>Error:</strong> Failed to store some price records in database
        //           </div>";
        // }

        // Display all the combined results (test)
        // echo "<h3>All Merchants (Total: " . count($all_merchants) . ")</h3>";
        // echo "<table border='1' cellpadding='5'>";
        // echo "<tr><th>Merchant ID</th><th>Merchant Name</th><th>Set Price</th><th>Update Date</th></tr>";
        
        // foreach ($all_merchants as $item) {
        //     echo "<tr>";
        //     echo "<td>" . (isset($item['shopID']) ? $item['shopID'] : 'N/A') . "</td>";
        //     echo "<td>" . (isset($item['shopName']) ? $item['shopName'] : 'N/A') . "</td>";
        //     echo "<td>HK$ " . (isset($item['price']) ? $item['price'] : 'N/A') . "</td>";
        //     echo "<td>" . (isset($item['updateDate']) ? $item['updateDate'] : 'N/A') . "</td>";
        //     echo "</tr>";
        // }
        // echo "</table>";
        
        // // DEBUG: Display price comparison results
        // echo "<h3>DEBUG: Price Comparison Results</h3>";
        // echo "<h4>Product Info: {$product['product_name']} (ID: {$product['product_id']})</h4>";
        // echo "<p>Reference Price: HK$ {$product['reference_price']}<br>";
        // echo "Acceptable Range: HK$ {$product['min_acceptable_price']} - HK$ {$product['max_acceptable_price']}</p>";
        
        // echo "<h4>Price Statistics:</h4>";
        // echo "<ul>";
        // echo "<li>Total merchants: {$comparison_results['stats']['total']}</li>";
        // echo "<li>Overpriced: {$comparison_results['stats']['overpriced_count']}</li>";
        // echo "<li>Underpriced: {$comparison_results['stats']['underpriced_count']}</li>";
        // echo "<li>Acceptable: {$comparison_results['stats']['acceptable_count']}</li>";
        // echo "<li>Missing price: {$comparison_results['stats']['missing_count']}</li>";
        // echo "</ul>";
        
        // // Display overpriced merchants
        // if (!empty($comparison_results['overpriced'])) {
        //     echo "<h4>Overpriced Merchants ({$comparison_results['stats']['overpriced_count']}):</h4>";
        //     echo "<table border='1' cellpadding='5' style='background-color: #ffdddd;'>";
        //     echo "<tr><th>Merchant ID</th><th>Merchant Name</th><th>Price</th><th>Status</th></tr>";
        //     foreach ($comparison_results['overpriced'] as $merchant) {
        //         echo "<tr>";
        //         echo "<td>" . (isset($merchant['shopID']) ? $merchant['shopID'] : 'N/A') . "</td>";
        //         echo "<td>" . (isset($merchant['shopName']) ? $merchant['shopName'] : 'N/A') . "</td>";
        //         echo "<td>HK$ " . (isset($merchant['price']) ? $merchant['price'] : 'N/A') . "</td>";
        //         echo "<td>" . (isset($merchant['price_status']) ? $merchant['price_status'] : 'N/A') . "</td>";
        //         echo "</tr>";
        //     }
        //     echo "</table>";
        // }
        
        // // Display underpriced merchants
        // if (!empty($comparison_results['underpriced'])) {
        //     echo "<h4>Underpriced Merchants ({$comparison_results['stats']['underpriced_count']}):</h4>";
        //     echo "<table border='1' cellpadding='5' style='background-color: #ddffdd;'>";
        //     echo "<tr><th>Merchant ID</th><th>Merchant Name</th><th>Price</th><th>Status</th></tr>";
        //     foreach ($comparison_results['underpriced'] as $merchant) {
        //         echo "<tr>";
        //         echo "<td>" . (isset($merchant['shopID']) ? $merchant['shopID'] : 'N/A') . "</td>";
        //         echo "<td>" . (isset($merchant['shopName']) ? $merchant['shopName'] : 'N/A') . "</td>";
        //         echo "<td>HK$ " . (isset($merchant['price']) ? $merchant['price'] : 'N/A') . "</td>";
        //         echo "<td>" . (isset($merchant['price_status']) ? $merchant['price_status'] : 'N/A') . "</td>";
        //         echo "</tr>";
        //     }
        //     echo "</table>";
        // }
        
        // record the crawl_log into db
        // $log_query = "INSERT INTO crawl_logs (platform_id, crawl_time, status, crawled_products, crawl_description) VALUES (?, NOW(), completed, )";
        // $stmt = $conn->prepare($log_query);
        // $stmt->bind_param("i", $_SESSION[""], $_SESSION[""]),

    } else {
        exit(Logger::error("Failed to fetch initial page!"));
    }
}

function fetch_url_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        Logger::warning("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $html = str_get_html($response);
    if (!$html) {
        Logger::warning("Failed to parse HTML!");
        return false;
    }
    
    // echo '<div style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($html) . '</div>';
    return $html;
}
// For price.com.hk
function retrieve_total_pages($Response) {
    $merchants_per_page = 20;
    if (preg_match('/共 (\d+) 個報價/', $Response, $matches)) {
        $totalMerchants = (int)$matches[1];
        $totalPages = ceil($totalMerchants/$merchants_per_page);
        return (int) $totalPages;
    } else {
        // Default to 1 page
        return 1;
    }
}

function retrieve_merchant_price_id($html) {
    $productData = array();
    $shopCount = 0;
    
    foreach ($html->find('.quotation-merchant-name') as $shopName) {
        $shop = array();
        $shop['shopName'] = trim($shopName->plaintext);
        $shopLink = $shopName->find('a', 0);
        if ($shopLink) {
            $href = $shopLink->href;
            if (preg_match('/s=(\d+)/', $href, $matches)) {
                $shop['shopID'] = $matches[1];
            } else {
                $href = str_replace("starshop.php?s=", "", $href);
                $href = str_replace("shop.php?s=", "", $href);
                $shop['shopID'] = $href;
            }
        } else {
            $shop['shopID'] = "unknown";
        }
        
        $productData[$shopCount] = $shop;
        $shopCount++;
    }
    
    $priceCount = 0;
    $skipFirst = true;
    foreach ($html->find('.product-price') as $price) {
        if ($skipFirst) {
            $skipFirst = false;
            continue;
        }
        if ($priceCount >= $shopCount) {
            continue;
        }
        $priceText = $price->plaintext;
        $priceText = str_replace("HK$", "", $priceText);
        $priceText = str_replace(",", "", $priceText);
        $priceText = str_replace(" ", "", $priceText);
        $productData[$priceCount]['price'] = trim($priceText);
        $priceCount++;
    }
    
    if ($priceCount == 0) {
        $priceCount = 0;
        foreach ($html->find('span.text-price-number') as $price) {
            if ($priceCount >= $shopCount+1 && $priceCount == 0) {
                $priceCount++;
            }    
            if ($priceCount >= $shopCount) {
                continue;
            }
            
            $priceText = $price->getAttribute('data-price');
            if (!$priceText) {
                $priceText = $price->plaintext;
            }
            
            $priceText = str_replace(",", "", $priceText);
            $productData[$priceCount]['price'] = trim($priceText);
            $priceCount++;
        }
    }
    
    $dateCount = 0;
    foreach ($html->find('.quote-source') as $d) {
        if ($dateCount >= $shopCount) {
            continue;
        }
        
        $dateText = $d->plaintext;
        $dateText = str_replace("更新日期：", "", $dateText);
        $dateText = str_replace("由星級商戶更新", "", $dateText);
        $dateText = str_replace("檢舉", "", $dateText);
        $dateText = str_replace("由商戶更新", "", $dateText);
        $dateText = str_replace("由會員更新", "", $dateText);
        $dateText = str_replace("更新", "", $dateText);
        $dateText = str_replace("請先查詢", "", $dateText);
        $dateText = str_replace("少量存貨", "", $dateText);
        $dateText = str_replace("大量現貨", "", $dateText);
        $productData[$dateCount]['updateDate'] = trim($dateText);
        $dateCount++;
    }
    
    return $productData;
}
?>

