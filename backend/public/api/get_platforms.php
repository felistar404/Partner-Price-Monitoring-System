<?php
// backend/public/api/get_platforms.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// query to get platforms 
$platforms_Query = "SELECT * FROM platforms";
$platformsResult = $conn->query($platforms_Query);

if (!$platformsResult) {
    echo json_encode(['records' => []]);
    exit;
} 

$records = [];
if ($platformsResult) {
    while ($row = $platformsResult->fetch_assoc()) {
        $records[] = $row;
    }
}

// Return the records
echo json_encode(['records' => $records]);
?>