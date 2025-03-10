<?php
// backend/public/api/get_price_records.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// query to get price records - get the most recent batch
$latestKeyQuery = "SELECT reference_key FROM main_records ORDER BY update_date DESC LIMIT 1";
$latestKeyResult = $conn->query($latestKeyQuery);

if ($latestKeyResult && $latestKeyResult->num_rows > 0) {
    $latestKeyRow = $latestKeyResult->fetch_assoc();
    $referenceKey = $latestKeyRow['reference_key'];
    
    // get records with joins to related tables
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
    // no reference key found
    echo json_encode(['records' => []]);
    exit;
}

$result = $conn->query($query);

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

// Return the records
echo json_encode(['records' => $records]);
?>