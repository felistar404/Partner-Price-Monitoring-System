<?php
// backend/public/api/get_platforms.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// query to get platforms 
$platforms_Query = "SELECT * FROM platforms";
$platformsResult = $conn->query($platforms_Query);

if (!$platformsResult) {
    http_response_code(500); // server side error
    echo json_encode(['error' => 'Database query failed', 'records' => []]);
    exit;
} 

$records = [];
if ($platformsResult) {
    while ($row = $platformsResult->fetch_assoc()) {
        $records[] = $row;
    }
}

// return the records
echo json_encode(['records' => $records]);
?>