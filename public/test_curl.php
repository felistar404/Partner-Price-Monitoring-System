<?php

require_once '../config/conn.php';
require_once '../lib/simple_html_dom.php';

// Base product URL
// $product_id = "606102";
// $base_url = "https://www.price.com.hk/product.php?p=" . $product_id;

// retrieve_and_display($base_url, $all_merchants);

// Init
$product_query = "SELECT * FROM products WHERE product_status = 'active'";
$stmt = $conn->prepare($product_query);
$stmt->execute();
$product_result = $stmt->get_result();
$p = $product_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($product_result->num_rows === 0) {
    exit("All products are inactive. System terminates.");
}

// If $p more than 0, meaning there are products waiting for monitoring.
foreach ($p as $product) {
    echo "Product ID: " . $product['product_id'] . "<br>";
    
    // Using $p['product_id'] to check existing sets of platform_id & platform_product_id (白話就是拿商品ID尋找它們屬於那些平台以及它對那些平台的ID是甚麼)
    $product_mapping_query = "SELECT platform_id, platform_product_id FROM product_url_mappings WHERE product_id = ?";
    $stmt = $conn->prepare($product_mapping_query);
    $stmt->bind_param("i", $product['product_id']);
    $stmt->execute();
    $product_mapping_result = $stmt->get_result();
    
    // If statements check if target product does belong with any platform.
    if ($product_mapping_result->num_rows > 0) {
        // Fetch all mappings for this product
        while ($mapping = $product_mapping_result->fetch_assoc()) {
            $platform_id = $mapping['platform_id'];
            $platform_product_id = $mapping['platform_product_id'];
            echo "|Test| Platform ID: " . $platform_id . ", Platform Product ID: " . $platform_product_id . "<br>";
            $platform_query = "SELECT platform_url, platform_url_price FROM platforms WHERE platform_id = ? AND platform_status = 'active'";
            $platform_stmt = $conn->prepare($platform_query);
            $platform_stmt->bind_param("i", $platform_id);
            $platform_stmt->execute();
            $platform_result = $platform_stmt->get_result();
            
            if ($platform_result->num_rows > 0) {
                $platform = $platform_result->fetch_assoc();
                $url = $platform['platform_url'] . $platform['platform_url_price'] . $platform_product_id;
                echo "|Test| Complete URL: " . $url . "<br>";
                // Uncomment to actually process the URL
                // retrieve_and_display($url);
                // perform comparison and alert system.
                // call function to record crawl_log.
            } else {
                echo "Platform ID " . $platform_id . " is inactive or does not exist<br>";
            }
            $platform_stmt->close();
        }
    } else {
        echo "No platform mappings found for product ID: " . $product['product_id'] . "<br>";
    }
    $stmt->close();
}


function retrieve_and_display($base_url) {
    $all_merchants = array();
    global $conn;
    $html = fetch_url_content($base_url);
    if ($html) {
        // process the first page
        $total_pages = retrieve_total_pages($html);
        echo "<h3>Total pages: $total_pages</h3>";
        
        // process first page results
        $page_results = retrieve_merchant_price_id($html);
        $all_merchants = array_merge($all_merchants, $page_results);
        
        // process of remaining pages
        for ($page = 2; $page <= $total_pages; $page++) {
            $page_url = $base_url . "&page=" . $page;
            echo "<p>Fetching page $page: $page_url</p>";

            // $page_html = fetch_url_content($page_url);
            // if ($page_html) {
            //     $page_results = retrieve_merchant_price_id($page_html);
            //     $all_merchants = array_merge($all_merchants, $page_results);
            // } else {
            //     echo "<p>Failed to fetch page $page</p>";
            // }
        } 
        
        // Display all the combined results
        echo "<h3>All Merchants (Total: " . count($all_merchants) . ")</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Merchant ID</th><th>Merchant Name</th><th>Set Price</th><th>Update Date</th></tr>";
        
        foreach ($all_merchants as $item) {
            echo "<tr>";
            echo "<td>" . (isset($item['shopID']) ? $item['shopID'] : 'N/A') . "</td>";
            echo "<td>" . (isset($item['shopName']) ? $item['shopName'] : 'N/A') . "</td>";
            echo "<td>HK$ " . (isset($item['price']) ? $item['price'] : 'N/A') . "</td>";
            echo "<td>" . (isset($item['updateDate']) ? $item['updateDate'] : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        // record the crawl_log into db
        // $log_query = "INSERT INTO crawl_logs (platform_id, crawl_time, status, crawled_products, crawl_description) VALUES (?, NOW(), completed, )";
        // $stmt = $conn->prepare($log_query);
        // $stmt->bind_param("i", $_SESSION[""], $_SESSION[""]),

    } else {
        echo "Failed to fetch initial page!";
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
    
    // echo '<div style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($html) . '</div>';
    return $html;
}
// For price.com.hk
function retrieve_total_pages($Response) {
    $merchants_per_page = 20;
    if (preg_match('/共 (\d+) 個報價/', $Response, $matches)) {
        $totalMerchants = (int)$matches[1];
        $totalPages = ceil($totalMerchants/$merchants_per_page);
        echo "TotalPages is: " . (int)$totalPages;
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
?>

