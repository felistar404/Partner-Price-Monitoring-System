<?php

require_once '../../../config/conn.php';
require_once '../../../lib/simple_html_dom.php';

// Fix the Logger class implementation - the previous version had issues
class Logger {
    public static function error($message) {
        echo "<div style='color: red; font-weight: bold;'>ERROR: $message</div>";
        return $message;
    }
    
    public static function warning($message) {
        echo "<div style='color: orange; font-weight: bold;'>WARNING: $message</div>";
        return $message;
    }
}

// Init
$reference_key = bin2hex(random_bytes(16));

// Add error reporting to help diagnose issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $product_query = "SELECT product_id, product_model, product_series, reference_price, 
                        min_acceptable_price, max_acceptable_price, product_description 
                        FROM products 
                        WHERE product_status = 'active'";
    $stmt = $conn->prepare($product_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    
    $stmt->execute();
    $product_result = $stmt->get_result();
    $p = $product_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // If no products, show error and exit
    if ($product_result->num_rows === 0) {
        echo "<div style='color: red; font-weight: bold;'>No products are available for monitoring.</div>";
        exit();
    }
    
    echo "<h1>Product Model and URL Check</h1>";
    echo "<table border='1'>";
    echo "<tr><th>Order</th><th>Product ID</th><th>Product Model</th><th>Platform</th><th>Full URL</th></tr>";
    $count = 0;

    // Main iteration
    foreach ($p as $product) {
        // Using $product['product_id'] to check platform mappings
        $mapping_query = "SELECT pum.platform_id, pum.platform_product_id,
                        p.platform_name, p.platform_url, p.platform_url_price 
                        FROM product_url_mappings pum 
                        JOIN platforms p ON pum.platform_id = p.platform_id 
                        WHERE pum.product_id = ? AND p.platform_status = 'active'";
        $stmt = $conn->prepare($mapping_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        
        $stmt->bind_param("i", $product['product_id']);
        $stmt->execute();
        $product_mapping_result = $stmt->get_result();
        $mapping_stmt = $product_mapping_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        if (empty($mapping_stmt)) {
            // echo "<tr>";
            // echo "<td>{$product['product_id']}</td>";
            // echo "<td>{$product['product_model']}</td>";
            // echo "<td colspan='2'>No platform mappings found for this product</td>";
            // echo "</tr>";
            continue;
            // echo "<div style='color: red;'>ERROR: The product: {$product['product_model']} (ID: {$product['product_id']}) does not belong to any platform in db record.</div>";
        } else {
            foreach ($mapping_stmt as $platform_info) {
                $count++;
                $platform_name = $platform_info['platform_name'];
                $prefix_url = $platform_info['platform_url'];
                $suffix_url = $platform_info['platform_url_price'];
                $p_id = $platform_info['platform_product_id'];
                $url = $prefix_url . $suffix_url . $p_id;
                
                echo "<tr>";
                echo "<th>{$count}</th>";
                echo "<td>{$product['product_id']}</td>";
                echo "<td>{$product['product_model']}</td>";
                echo "<td>{$platform_name}</td>";
                echo "<td><a href='{$url}' target='_blank'>{$url}</a></td>";
                echo "</tr>";
            }
        }
    }
    
    echo "</table>";
    echo "<p>URL check completed. No actual fetching or processing was performed.</p>";

} catch (Exception $e) {
    // Show detailed error information
    echo "<h2>Error Occurred</h2>";
    echo "<p style='color: red; font-weight: bold;'>" . $e->getMessage() . "</p>";
    echo "<p>Error on line: " . $e->getLine() . "</p>";
}
?>

