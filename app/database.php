<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Database connection script using PDO
 * This file establishes a connection to the MySQL database
 */

// Database configuration
$host = 'sas-nl.webhostmall.com';      // Database host
$dbname = 'pcit_crd_db';  // Database name
$username = 'pcit_crd_user';       // Database username
$password = 'crd_user';           // Database password 

try {
    // Create a new PDO instance
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Set error mode to exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Set default fetch mode to associative array
            PDO::ATTR_EMULATE_PREPARES => false,            // Use real prepared statements
        ]
    );
    
    // Connection successful
    // echo "Connected successfully to the database";
    
    // Return the PDO object for use in other files
    return $pdo;
    
} catch (PDOException $e) {
    // Handle connection errors
    die("Database connection failed: " . $e->getMessage());
}