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

// For actual POST requests, continue with the existing code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    include_once '../../../config/conn.php';
    
    // get data from frontend
    $data = json_decode(file_get_contents("php://input"));
    $response = array();
    
    // validate the data
    if(
        !empty($data->merchant_id) &&
        !empty($data->merchant_name) &&
        !empty($data->email) &&
        !empty($data->phone) &&
        !empty($data->address) &&
        !empty($data->merchant_status)
    ) {
        $merchant_id = htmlspecialchars(strip_tags($data->merchant_id));
        $merchant_name = htmlspecialchars(strip_tags($data->merchant_name));
        $email = htmlspecialchars(strip_tags($data->email));
        $phone = htmlspecialchars(strip_tags($data->phone));
        $address = htmlspecialchars(strip_tags($data->address));
        $merchant_status = htmlspecialchars(strip_tags($data->merchant_status));
    
        $query = "UPDATE merchants 
              SET merchant_name = ?, 
                  email = ?, 
                  phone = ?, 
                  address = ?, 
                  merchant_status = ?, 
                  updated_at = NOW() 
              WHERE merchant_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", 
            $merchant_name, 
            $email, 
            $phone, 
            $address, 
            $merchant_status,
            $merchant_id
        );
        if($stmt->execute()) {
            // success response
            $response["success"] = true;
            $response["message"] = "Merchant was updated successfully.";
            http_response_code(200);
        } else {
            // error in execution
            $response["success"] = false;
            $response["message"] = "Unable to update merchant.";
            http_response_code(503);
        }
    
        $stmt->close();
    } else {
        // required data is missing
        $response["success"] = false;
        $response["message"] = "Unable to update merchant. Data is incomplete.";
        http_response_code(400);
    }   
    echo json_encode($response);
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>