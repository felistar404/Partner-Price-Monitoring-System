<?php
// backend/public/api/get_merchants.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// query to get merchant 
$merchant_Query = "SELECT * FROM merchants";
$merchantResult = $conn->query($merchant_Query);

if (!$merchantResult) {
    http_response_code(500); // server side error
    echo json_encode(['error' => 'Database query failed', 'records' => []]);
    exit;
} 

$records = [];
if ($merchantResult) {
    while ($row = $merchantResult->fetch_assoc()) {
        $records[] = $row;
    }
}

// return the records
echo json_encode(['records' => $records]);
?>