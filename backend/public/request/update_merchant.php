<?php
// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
include_once 'config/database.php';

// Get database connection
$database = new Database();
$conn = $database->getConnection();

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Prepare response array
$response = array();

// Check if required data exists
if(
    !empty($data->merchant_id) &&
    !empty($data->merchant_name) &&
    !empty($data->email) &&
    !empty($data->phone) &&
    !empty($data->address) &&
    !empty($data->merchant_status)
) {
    // Sanitize input
    $merchant_id = htmlspecialchars(strip_tags($data->merchant_id));
    $merchant_name = htmlspecialchars(strip_tags($data->merchant_name));
    $email = htmlspecialchars(strip_tags($data->email));
    $phone = htmlspecialchars(strip_tags($data->phone));
    $address = htmlspecialchars(strip_tags($data->address));
    $merchant_status = htmlspecialchars(strip_tags($data->merchant_status));
    
    // Update query
    $query = "UPDATE merchants 
              SET merchant_name = :merchant_name, 
                  email = :email, 
                  phone = :phone, 
                  address = :address, 
                  merchant_status = :merchant_status, 
                  updated_at = NOW() 
              WHERE merchant_id = :merchant_id";
    
    // Prepare statement
    $stmt = $conn->prepare($query);
    
    // Bind values
    $stmt->bindParam(":merchant_id", $merchant_id);
    $stmt->bindParam(":merchant_name", $merchant_name);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":address", $address);
    $stmt->bindParam(":merchant_status", $merchant_status);
    
    // Execute query
    if($stmt->execute()) {
        // Success response
        $response["success"] = true;
        $response["message"] = "Merchant was updated successfully.";
        http_response_code(200);
    } else {
        // Error in execution
        $response["success"] = false;
        $response["message"] = "Unable to update merchant.";
        http_response_code(503);
    }
} else {
    // Required data is missing
    $response["success"] = false;
    $response["message"] = "Unable to update merchant. Data is incomplete.";
    http_response_code(400);
}

// Return response as JSON
echo json_encode($response);
?>
