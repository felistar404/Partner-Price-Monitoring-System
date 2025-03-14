<?php
// backend/public/api/get_latest_date.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Query to get the latest update date from main_records
$query = "SELECT update_date FROM main_records ORDER BY update_date DESC LIMIT 1";
$result = $conn->query($query);

$response = ['latest_update' => null];

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['latest_update'] = $row['update_date'];
}

// Return the latest update date
echo json_encode($response);
?>
