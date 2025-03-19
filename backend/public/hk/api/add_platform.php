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
    // include database connection
    include_once '../../../config/conn.php';
    
    // get data from frontend
    $data = json_decode(file_get_contents(filename: "php://input"));
    $response = array();
    
    // validate the data
    if(
        !empty($data->platform_name) &&
        !empty($data->platform_url) &&
        !empty($data->platform_url_price) &&
        !empty($data->platform_url_merchant) &&
        !empty($data->platform_status)
    ) {
        $platform_name = htmlspecialchars(strip_tags($data->platform_name));
        $platform_url = htmlspecialchars(strip_tags($data->platform_url));
        $platform_url_price = htmlspecialchars(strip_tags($data->platform_url_price));
        $platform_url_merchant = htmlspecialchars(strip_tags($data->platform_url_merchant));
        $platform_status = htmlspecialchars(strip_tags($data->platform_status));
    
        $query = "INSERT INTO platforms 
                (platform_name, platform_url, platform_url_price, platform_url_merchant, platform_status) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", 
            $platform_name, 
            $platform_url, 
            $platform_url_price, 
            $platform_url_merchant, 
            $platform_status
        );
        if($stmt->execute()) {
            // success response
            $response["success"] = true;
            $response["message"] = "platform was added successfully.";
            http_response_code(200);
        } else {
            // error in execution
            $response["success"] = false;
            $response["message"] = "Unable to add platform. " . $conn->error;
            http_response_code(503);
        }
    
        $stmt->close();
    } else {
        // required data is missing
        $response["success"] = false;
        $response["message"] = "Unable to add platform. Data is incomplete.";
        http_response_code(400);
    }   
    echo json_encode($response);
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>