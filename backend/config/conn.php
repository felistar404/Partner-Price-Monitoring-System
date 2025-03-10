<?php
// Database login details
$host = "localhost";
$username = "root";
$password = "";
$database = "price_monitoring_system";

// PDO approach
// try {
//     // PDO instance
//     $dsn = "mysql:host=$host;dbname=$database";
//     $pdo = new PDO($dsn, $username, $password);

//     // Set the PDO error mode to exception
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     echo "Connected successfully! \n";
// } catch (PDOException $e) {
//     die("Connection failed: " . $e->getMessage());
// }

// MySQLI Approach
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>