<?php

require_once '../config/conn.php';

// URLs
$test_url = "https://www.price.com.hk/product.php?p=606102";
$test_url1 = "https://www.wcslmall.com/search?type=product&q=qnap";
$test_url3 = "https://www.price.com.hk/product.php?p=606102&page=2";

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
    for ($page=1; $page<=$totalPages; $page++) {

    }
    echo '<div style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($cleanedResponse) . '</div>';
}


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






// Close the cURL session
curl_close($ch);
?>