<?php
require_once '../config/conn.php';
require_once '../lib/simple_html_dom.php';

function monitor_product_prices($product_id) {
    global $conn;
    
    // 步驟1：從資料庫獲取產品資訊
    $product_query = "SELECT * FROM products WHERE product_id = ? AND product_status = 'active'";
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    
    if ($product_result->num_rows === 0) {
        echo "產品不存在或未啟用！";
        return;
    }
    
    $product = $product_result->fetch_assoc();
    $stmt->close();
    
    echo "<h2>產品：{$product['product_name']} (型號：{$product['product_model']})</h2>";
    echo "<p>參考價格：HK$ {$product['reference_price']}</p>";
    echo "<p>可接受價格範圍：HK$ {$product['min_acceptable_price']} - HK$ {$product['max_acceptable_price']}</p>";
    
    // 步驟2：尋找該產品在哪些平台上有賣
    $platforms_query = "SELECT pum.platform_id, pum.platform_product_id, 
                       p.platform_name, p.platform_url, p.platform_url_price, p.platform_url_merchant
                       FROM product_url_mappings pum 
                       JOIN platforms p ON pum.platform_id = p.platform_id
                       WHERE pum.product_id = ? AND p.platform_status = 'active'";
    $stmt = $conn->prepare($platforms_query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $platforms_result = $stmt->get_result();
    
    if ($platforms_result->num_rows === 0) {
        echo "沒有找到該產品在任何平台上的資訊！";
        return;
    }
    
    // 存儲產品在各平台的資訊 (listA)
    $platforms_list = array();
    while ($row = $platforms_result->fetch_assoc()) {
        $platforms_list[] = $row;
    }
    $stmt->close();
    
    // 開始記錄爬蟲日誌
    $log_ids = array();
    foreach ($platforms_list as $platform) {
        $log_query = "INSERT INTO crawl_logs (platform_id, start_time, status) VALUES (?, NOW(), 'running')";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param("i", $platform['platform_id']);
        $stmt->execute();
        $log_ids[$platform['platform_id']] = $conn->insert_id;
        $stmt->close();
    }
    
    // 步驟3：迴圈處理每個平台
    foreach ($platforms_list as $platform) {
        echo "<h3>平台：{$platform['platform_name']}</h3>";
        
        // 根據平台 URL 和產品 ID 構建完整的 URL
        $base_url = $platform['platform_url'] . $platform['platform_url_price'] . $platform['platform_product_id'];
        echo "<p>產品 URL：<a href='{$base_url}' target='_blank'>{$base_url}</a></p>";
        
        // 獲取第一頁內容
        $html = fetch_url_content($base_url);
        if (!$html) {
            update_crawl_log($log_ids[$platform['platform_id']], 'failed', "無法獲取頁面內容");
            echo "<p>錯誤：無法獲取頁面內容！</p>";
            continue;
        }
        
        // 獲取總頁數
        $total_pages = retrieve_total_pages($html);
        echo "<p>共 {$total_pages} 頁商戶資料</p>";
        
        // 初始化商戶資料陣列
        $all_merchants = array();
        
        // 處理第一頁的商戶資料
        $page_merchants = retrieve_merchant_price_id($html);
        $all_merchants = array_merge($all_merchants, $page_merchants);
        
        // 處理剩餘頁面
        for ($page = 2; $page <= $total_pages; $page++) {
            $page_url = $base_url . "&page=" . $page;
            $page_html = fetch_url_content($page_url);
            
            if ($page_html) {
                $page_merchants = retrieve_merchant_price_id($page_html);
                $all_merchants = array_merge($all_merchants, $page_merchants);
            } else {
                echo "<p>無法獲取第 {$page} 頁內容！</p>";
            }
        }
        
        // 創建表格顯示所有商戶
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>商戶 ID</th>
                <th>商戶名稱</th>
                <th>標價</th>
                <th>更新日期</th>
                <th>價格狀態</th>
                <th>商戶聯絡資料</th>
              </tr>";
        
        $success_count = 0;
        $error_count = 0;
        
        // 處理每個商戶
        foreach ($all_merchants as $merchant) {
            // 如果沒有商戶 ID 或價格，跳過
            if (!isset($merchant['shopID']) || !isset($merchant['price']) || $merchant['shopID'] == 'unknown') {
                $error_count++;
                continue;
            }
            
            // 查詢資料庫獲取真實商戶 ID
            $merchant_query = "SELECT m.merchant_id, m.merchant_name, m.email, m.phone, m.address 
                             FROM platform_merchant_mappings pmm 
                             JOIN merchants m ON pmm.merchant_id = m.merchant_id 
                             WHERE pmm.platform_id = ? AND pmm.platform_merchant_id = ?";
            $stmt = $conn->prepare($merchant_query);
            $stmt->bind_param("is", $platform['platform_id'], $merchant['shopID']);
            $stmt->execute();
            $merchant_result = $stmt->get_result();
            
            // 設定價格狀態
            $price = floatval(trim($merchant['price']));
            $price_status = "normal";
            $status_class = "normal";
            
            if ($price < $product['min_acceptable_price']) {
                $price_status = "underpriced";
                $status_class = "underpriced";
            } elseif ($price > $product['max_acceptable_price']) {
                $price_status = "overpriced";
                $status_class = "overpriced";
            }
            
            // 設定更新日期
            $update_date = isset($merchant['updateDate']) ? $merchant['updateDate'] : '';
            $update_timestamp = null;
            
            if (!empty($update_date)) {
                // 轉換日期格式 "yyyy-mm-dd" 為 MySQL timestamp
                $date_parts = explode('-', $update_date);
                if (count($date_parts) === 3) {
                    $update_timestamp = "{$date_parts[0]}-{$date_parts[1]}-{$date_parts[2]} 00:00:00";
                }
            }
            
            // 顯示商戶資料
            echo "<tr class='{$status_class}'>";
            echo "<td>" . $merchant['shopID'] . "</td>";
            echo "<td>" . $merchant['shopName'] . "</td>";
            echo "<td>HK$ " . $price . "</td>";
            echo "<td>" . $update_date . "</td>";
            echo "<td>" . $price_status . "</td>";
            
            // 如果有商戶資料，顯示聯絡資訊
            if ($merchant_result->num_rows > 0) {
                $db_merchant = $merchant_result->fetch_assoc();
                echo "<td>
                        <strong>{$db_merchant['merchant_name']}</strong><br>
                        電郵：{$db_merchant['email']}<br>
                        電話：{$db_merchant['phone']}<br>
                        地址：{$db_merchant['address']}
                      </td>";
                
                // 記錄價格到資料庫
                $price_record_query = "INSERT INTO price_records 
                                      (product_id, merchant_id, platform_id, price, price_status, update_date) 
                                      VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($price_record_query);
                $insert_stmt->bind_param("iiidss", 
                    $product_id, 
                    $db_merchant['merchant_id'], 
                    $platform['platform_id'], 
                    $price, 
                    $price_status,
                    $update_timestamp
                );
                $insert_stmt->execute();
                $insert_stmt->close();
                
                $success_count++;
            } else {
                echo "<td>未映射商戶：此商戶 ID 在資料庫中沒有對應記錄</td>";
                $error_count++;
            }
            
            echo "</tr>";
            $stmt->close();
        }
        
        echo "</table>";
        
        // 更新爬蟲日誌
        update_crawl_log(
            $log_ids[$platform['platform_id']], 
            'completed', 
            null, 
            count($all_merchants), 
            $success_count, 
            $error_count
        );
    }
    
    // 更新冷卻時間
    foreach ($platforms_list as $platform) {
        $next_available_time = date('Y-m-d H:i:s', strtotime("+24 hours"));
        
        $cooldown_query = "INSERT INTO refresh_cooldowns 
                          (platform_id, last_refresh_time, next_available_time, cooldown_hours) 
                          VALUES (?, NOW(), ?, 24) 
                          ON DUPLICATE KEY UPDATE 
                          last_refresh_time = NOW(), 
                          next_available_time = VALUES(next_available_time)";
        $stmt = $conn->prepare($cooldown_query);
        $stmt->bind_param("is", $platform['platform_id'], $next_available_time);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * 更新爬蟲日誌
 */
function update_crawl_log($log_id, $status, $error_message = null, $crawled_products = 0, $success_count = 0, $error_count = 0) {
    global $conn;
    
    $query = "UPDATE crawl_logs 
              SET end_time = NOW(), 
                  status = ?, 
                  crawled_products = ?, 
                  success_count = ?, 
                  error_count = ?, 
                  error_message = ? 
              WHERE log_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siiisi", $status, $crawled_products, $success_count, $error_count, $error_message, $log_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * 獲取 URL 內容
 */
function fetch_url_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch);
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $html = str_get_html($response);
    if (!$html) {
        echo "Failed to parse HTML!";
        return false;
    }
    
    return $html;
}

/**
 * 獲取總頁數
 */
function retrieve_total_pages($cleanedResponse) {
    $merchants_per_page = 20;
    if (preg_match('/共(\d+)個報價/', $cleanedResponse, $matches)) {
        $totalMerchants = (int)$matches[1];
        $totalPages = ceil($totalMerchants/$merchants_per_page);
        return (int) $totalPages;
    } else {
        // Default to 1 page
        return 1;
    }
}

/**
 * 提取商戶 ID 和價格
 */
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
    foreach ($html->find('.product-price') as $price) {
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

// 添加一些 CSS 樣式
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
    th { background-color: #f2f2f2; }
    td, th { padding: 8px; text-align: left; border: 1px solid #ddd; }
    h2 { color: #333; }
    h3 { color: #0066cc; margin-top: 20px; }
    .underpriced { background-color: #ffdddd; }
    .overpriced { background-color: #ddffdd; }
    .normal { }
</style>';

// 執行價格監控
if (isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    monitor_product_prices($product_id);
} else {
    // 如果沒有提供產品 ID，顯示所有活躍產品清單
    global $conn;
    $query = "SELECT product_id, product_name, product_model, reference_price FROM products WHERE product_status = 'active'";
    $result = $conn->query($query);
    
    echo "<h2>請選擇要監控的產品：</h2>";
    echo "<table border='1'>";
    echo "<tr><th>產品 ID</th><th>產品名稱</th><th>型號</th><th>參考價格</th><th>操作</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['product_name'] . "</td>";
        echo "<td>" . $row['product_model'] . "</td>";
        echo "<td>HK$ " . $row['reference_price'] . "</td>";
        echo "<td><a href='?product_id=" . $row['product_id'] . "'>監控價格</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
}
?>