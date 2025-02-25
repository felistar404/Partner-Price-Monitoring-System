<?php
// URL
$test_url = "https://www.price.com.hk/product.php?p=606102";
$test_url1 = "https://www.wcslmall.com/search?type=product&q=qnap";


// Initialize a cURL session
$ch = curl_init($test_url1);

// Set options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7 (.NET CLR 3.5.30729)");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_MAXREDIRS,      4);

// Execute the cURL session
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
} else {
    // print HTTP status
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status Code: $httpStatus<br><br>";
    echo "<h1>Response from $url:</h1>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

// Close the cURL session
curl_close($ch);
?>