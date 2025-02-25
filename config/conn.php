<?php
// Database login details
$host = "localhost";
$username = "root";
$password = "";
$database = "price_monitoring_system";

try {
    // PDO instance
    $dsn = "mysql:host=$host;dbname=$database";
    $pdo = new PDO($dsn, $username, $password);

    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully! \n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>