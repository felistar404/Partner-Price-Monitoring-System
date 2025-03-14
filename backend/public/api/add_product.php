<?php
// Headers for REST API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request <--- CORS policy
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // exit with 200 OK status
    http_response_code(200);
    exit;
}

// align with frontend code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    include_once '../../config/conn.php';
    
    // get data from frontend
    $data = json_decode(file_get_contents(filename: "php://input"));
    $response = array();
    
    // validate the data
    if(
        !empty($data->product_name) &&
        !empty($data->product_model) &&
        isset($data->reference_price) &&
        isset($data->min_acceptable_price) &&
        isset($data->max_acceptable_price) &&
        !empty($data->product_status)
    ) {
        $product_name = htmlspecialchars(strip_tags($data->product_name));
        $product_model = htmlspecialchars(strip_tags($data->product_model));
        $reference_price = htmlspecialchars(strip_tags($data->reference_price));
        $min_acceptable_price = htmlspecialchars(strip_tags($data->min_acceptable_price));
        $max_acceptable_price = htmlspecialchars(strip_tags($data->max_acceptable_price));
        $product_status = htmlspecialchars(strip_tags($data->product_status));
        $product_description = htmlspecialchars(strip_tags($data->product_description ?? ''));
    
        $query = "INSERT INTO products 
                (product_name, product_model, reference_price, min_acceptable_price, max_acceptable_price, product_status, product_description) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdddss", 
            $product_name, 
            $product_model, 
            $reference_price, 
            $min_acceptable_price, 
            $max_acceptable_price, 
            $product_status, 
            $product_description
        );
        if($stmt->execute()) {
            // success response
            $response["success"] = true;
            $response["message"] = "Product was added successfully.";
            http_response_code(200);
        } else {
            // error in execution
            $response["success"] = false;
            $response["message"] = "Unable to add product. " . $conn->error;
            http_response_code(503);
        }
    
        $stmt->close();
    } else {
        // required data is missing
        $response["success"] = false;
        $response["message"] = "Unable to add product. Data is incomplete.";
        http_response_code(400);
    }   
    echo json_encode($response);
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>