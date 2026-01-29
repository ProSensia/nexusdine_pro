<?php
define('DB_HOST', 'premium281.web-hosting.com');
define('DB_USER', 'prosdfwo_nexuspro');
define('DB_PASS', 'NexusPro@2026');
define('DB_NAME', 'prosdfwo_nexuspro');
define('BASE_URL', 'https://restaurant.prosensia.pk/');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>