<?php
// backend/public/api/get_product_mappings.php
require_once '../../config/conn.php';

// headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only GET method is allowed']);
    exit;
}

// query to get product mappings with related product and platform information
$query = "SELECT pum.mapping_id, pum.product_id, pum.platform_id, pum.platform_product_id, 
          pum.created_at, pum.updated_at,
          p.product_name, p.product_model,
          pl.platform_name, pl.platform_url, pl.platform_url_price
          FROM product_url_mappings pum
          JOIN products p ON pum.product_id = p.product_id
          JOIN platforms pl ON pum.platform_id = pl.platform_id
          ORDER BY pum.product_id, pum.platform_id";

$result = $conn->query($query);

if (!$result) {
    http_response_code(500); // server side error
    echo json_encode([
        'success' => false, 
        'error' => 'Database query failed: ' . $conn->error, 
        'records' => []
    ]);
    exit;
}

$records = [];
while ($row = $result->fetch_assoc()) {
    // Calculate the full URL for convenience
    $row['full_url'] = $row['platform_url'] . $row['platform_url_price'] . $row['platform_product_id'];
    $records[] = $row;
}

// Return the records with success status
echo json_encode([
    'success' => true,
    'records' => $records
]);
?>