<?php

require_once '../config/conn.php';

// URLs
$test_url = "https://www.price.com.hk/product.php?p=606102";
// $test_url1 = "https://www.wcslmall.com/search?type=product&q=qnap";
$test_url3 = "https://www.price.com.hk/product.php?p=606102&page=2";
$test_url4 = "https://www.price.com.hk/product.php?p=346731";

// Initialize Curl session
$ch = curl_init($test_url);

// Set options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_MAXREDIRS,      4);

// Start Curl session
$response = curl_exec($ch);

// Error handling
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
} else {
    // check HTTP status
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // echo "HTTP Status Code: $httpStatus<br><br>";
    $cleanedResponse = preg_replace('/\s+/', '', $response);
    // echo "<h1>Raw HTML Response from $test_url:</h1>";
    $totalPages = retrieve_total_pages($cleanedResponse);
    // for ($page=1; $page<=$totalPages; $page++) {
    retrieve_merchant_price_id( $cleanedResponse);
    // }
    echo '<div style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($cleanedResponse) . '</div>';
}

// For price.com.hk
function retrieve_total_pages($cleanedResponse) {
    $merchants_per_page = 20;
    if (preg_match('/共(\d+)個報價/', $cleanedResponse, $matches)) {
        $totalMerchants = (int)$matches[1];
        $totalPages = ceil($totalMerchants/$merchants_per_page);
        return (int) $totalPages;
    } else {
        // echo "Could not retrieve the total number of merchants.";
        return 0;
    }
}


function retrieve_merchant_price_id($cleanedResponse) {
    $merchant = [];
    $price = [];
    // if(preg_match_all('/"quotation-merchant-name"><ahref="starshop.php?s=(\d+)"/', $cleanedResponse, $matches)) {
    //     foreach ($matches[1] as $merchant_id) {
    //         $merchant[] = (string)$merchant_id;
    //     }
    // } else {
    //     echo "not found merchant name";
    // }
    if(preg_match('/共(\d+)個報價/', $cleanedResponse, $matches)) {
        foreach ($matches[1] as $merchant_id) {
            $merchant[] = (string)$merchant_id;
        }
    } else {
        echo "not found merchant name";
    }
    if(preg_match_all('/HK<\/span><span class="text-price-number"data-price="(\d+)/', $cleanedResponse, $matches)) {
        foreach ($matches[1] as $prices) {
            $price[] = (int)$prices;
        }
    } else {
        echo "not found price name";
    }
    print_r($merchant);
    print_r($price); 
}





// Close the cURL session
curl_close($ch);
?>