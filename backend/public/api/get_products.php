<?php
// backend/public/api/get_products.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// query to get products 
$products_Query = "SELECT * FROM products";
$productsResult = $conn->query($products_Query);

if (!$productsResult) {
    echo json_encode(['records' => []]);
    exit;
} 

$records = [];
if ($productsResult) {
    while ($row = $productsResult->fetch_assoc()) {
        $records[] = $row;
    }
}

// Return the records
echo json_encode(['records' => $records]);
?>