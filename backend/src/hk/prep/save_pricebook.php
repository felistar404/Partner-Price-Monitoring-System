<?php
require_once '../../../config/conn.php';
require_once './pricebook.php';

$decodedData = json_decode($data, true);

file_put_contents('../temp/raw_pricebook_response.json', $data);

if ($decodedData === null) {
    $jsonError = json_last_error_msg();
    die(json_encode([
        'error' => 'Failed to parse JSON response',
        'json_error' => $jsonError,
        'raw_data_preview' => substr($data, 0, 500)
    ]));
}

$conn->query("DELETE FROM products");

$products = [];
$productsInserted = 0;
$productsUpdated = 0;
$productsSkipped = 0;

if (isset($decodedData['returnData']) && is_array($decodedData['returnData'])) {
    foreach ($decodedData['returnData'] as $item) {
        if (isset($item['nas_pn'])) {
            $product = [
                'nas_pn' => $item['nas_pn'],
                'nas_series' => $item['nas_series'],
                'description' => isset($item['nas_description']) ? $item['nas_description'] : '',
                'dp' => null,
                'srp' => null
            ];
            
            if (isset($item['price']) && is_array($item['price']) && !empty($item['price'])) {
                $latestPrice = $item['price'][0];
                
                if (isset($latestPrice['dp'])) {
                    $product['dp'] = $latestPrice['dp'];
                }
                
                if (isset($latestPrice['srp'])) {
                    $product['srp'] = $latestPrice['srp'];
                }
            }
            
            if ($product['dp'] !== null && $product['dp'] !== 0 && 
                $product['srp'] !== null && $product['srp'] !== 0) {
                
                $products[] = $product;
                
                $referencePrice = $product['srp'];
                $minAcceptablePrice = round($referencePrice * 0.97, 0);
                $maxAcceptablePrice = round($referencePrice * 1.03, 0);
                
                $stmt = $conn->prepare("INSERT INTO products 
                    (product_model, product_series, reference_price, min_acceptable_price, max_acceptable_price, product_description) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssddds", 
                    $product['nas_pn'],
                    $product['nas_series'],
                    $referencePrice,
                    $minAcceptablePrice,
                    $maxAcceptablePrice,
                    $product['description']
                );
                
                if ($stmt->execute()) {
                    $productsInserted++;
                } else {
                    error_log("Error inserting product: " . $stmt->error);
                    $productsSkipped++;
                }
                
                $stmt->close();
            } else {
                $productsSkipped++;
            }
        }
    }
    
    $conn->close();
    
} else {
    file_put_contents('../temp/unexpected_structure.json', json_encode($decodedData, JSON_PRETTY_PRINT));
    die(json_encode([
        'error' => 'Unexpected data structure',
        'structure_preview' => json_encode(array_keys($decodedData))
    ]));
}

$filteredFilePath = '../temp/non_zero_products.json';
file_put_contents($filteredFilePath, json_encode([
    'count' => count($products),
    'data' => $products
], JSON_PRETTY_PRINT));

header('Content-Type: application/json');
echo json_encode([
    'status' => 1,
    'count' => count($products),
    'inserted' => $productsInserted,
    'updated' => $productsUpdated,
    'skipped' => $productsSkipped,
    'filtered_file' => $filteredFilePath
]);
?>