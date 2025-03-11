<?php
// backend/public/api/get_merchants.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// query to get merchant 
$merchant_Query = "SELECT merchant_name, email, phone, address, merchant_status FROM merchants";
$merchantResult = $conn->query($merchant_Query);

if (!$merchantResult) {
    echo json_encode(['records' => []]);
    exit;
} 

$records = [];
if ($merchantResult) {
    while ($row = $merchantResult->fetch_assoc()) {
        $records[] = $row;
    }
}

// Return the records
echo json_encode(['records' => $records]);
?>