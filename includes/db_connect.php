<?php
require_once 'config/database.php';

function getPDOConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function testConnection() {
    try {
        $pdo = getPDOConnection();
        echo "Database connection successful!";
        return true;
    } catch (Exception $e) {
        echo "Connection failed: " . $e->getMessage();
        return false;
    }
}

// Auto-create database if it doesn't exist
function createDatabaseIfNotExists() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        // echo "Database created or already exists<br>";
    } else {
        die("Error creating database: " . $conn->error);
    }
    
    $conn->close();
}

// Create tables if they don't exist
function createTablesIfNotExist() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Read the SQL schema file
    $sql_file = __DIR__ . '/../nexusdine_pro.sql';
    
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            // echo "Tables created successfully<br>";
        } else {
            echo "Error creating tables: " . $conn->error;
        }
    } else {
        // Create tables manually if file doesn't exist
        include_once 'functions.php';
        // Basic tables will be created through the registration process
    }
    
    $conn->close();
}

// Initialize database on first run
function initializeDatabase() {
    createDatabaseIfNotExists();
    createTablesIfNotExist();
}

// Check if this is first run
function isFirstRun() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        return true;
    }
    
    $result = $conn->query("SHOW TABLES LIKE 'businesses'");
    $is_first_run = $result->num_rows === 0;
    
    $conn->close();
    return $is_first_run;
}

// Create admin user if no users exist
function createDefaultAdmin() {
    if (isFirstRun()) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Create default business
        $business_sql = "INSERT INTO businesses (business_name, business_type, email, subscription_plan) 
                         VALUES ('Demo Restaurant', 'restaurant', 'admin@nexusdine.com', 'pro')";
        $conn->query($business_sql);
        $business_id = $conn->insert_id;
        
        // Create admin user
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_sql = "INSERT INTO staff (business_id, name, email, password, role) 
                      VALUES ($business_id, 'Administrator', 'admin@nexusdine.com', '$hashed_password', 'admin')";
        $conn->query($admin_sql);
        
        $conn->close();
        
        return true;
    }
    return false;
}

// Test and initialize on include
try {
    initializeDatabase();
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}
?>