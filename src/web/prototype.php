<?php

require_once '../../config/conn.php';

// URLs
$test_url = "https://www.price.com.hk/product.php?p=606102";
// $test_url1 = "https://www.wcslmall.com/search?type=product&q=qnap";
$test_url3 = "https://www.price.com.hk/product.php?p=606102&page=2";
$test_url4 = "https://www.price.com.hk/product.php?p=346731";
$cookieFile = '../tmp/cookie.txt';

// Initialize Curl session
// $ch = curl_init($test_url4);

// Useragents
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
];
$userAgent = $userAgents[array_rand($userAgents)];

// Set options
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
// curl_setopt($ch, CURLOPT_MAXREDIRS,      4);
// curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
// curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);;
// curl_setopt($ch, CURLOPT_HTTPHEADER, [
//     'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
//     'Accept-Language: en-US,en;q=0.5',
//     'Connection: keep-alive',
//     'Upgrade-Insecure-Requests: 1',
//     'Referer: https://www.google.com/'
// ]);

// Start Curl session
$response = shell_exec('node fetch.js');
echo $response;

// Error handling
if (!$response) {
    // echo "cURL Error: " . curl_error($ch);
    echo('123');
} else {
    // check HTTP status
    // $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status Code: $httpStatus<br><br>";
    $cleanedResponse = preg_replace('/\s+/', '', $response);
    // echo "<h1>Raw HTML Response from $test_url:</h1>";
    $totalPages = retrieve_total_pages($cleanedResponse);
    // for ($page=1; $page<=$totalPages; $page++) {
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
// curl_close($ch);
?>