<?php
// backend/public/api/get_price_records.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development only

// Query to get price records
$latestKeyQuery = "SELECT reference_key FROM main_records ORDER BY update_date DESC LIMIT 1";
$latestKeyResult = $conn->query($latestKeyQuery);

if ($latestKeyResult && $latestKeyResult->num_rows > 0) {
    $latestKeyRow = $latestKeyResult->fetch_assoc();
    $referenceKey = $latestKeyRow['reference_key'];
    
    $query = "SELECT pr.*, 
              p.product_name, p.product_model, 
              m.merchant_name, 
              pl.platform_name 
              FROM price_records pr
              JOIN products p ON pr.product_id = p.product_id
              JOIN merchants m ON pr.merchant_id = m.merchant_id
              JOIN platforms pl ON pr.platform_id = pl.platform_id
              WHERE pr.reference_key = '$referenceKey'";
} else {
    // return empty array if no records in db
    echo json_encode([]);
    exit;
}

$result = $conn->query($query);

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

echo json_encode($records);
?>