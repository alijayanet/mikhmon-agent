<?php
/*
 * Database Configuration for Agent System
 * MikhMon WhatsApp Integration
 */

// Database credentials untuk HOSTING
// PENTING: Ganti dengan kredensial database hosting Anda
// Format biasanya: namauser_namadb
define('DB_HOST', 'localhost'); // atau IP hosting
define('DB_USER', 'root'); // <-- GANTI dengan username database hosting
define('DB_PASS', ''); // <-- GANTI dengan password database hosting
define('DB_NAME', 'mikhmon-server'); // <-- GANTI dengan nama database di hosting
define('DB_CHARSET', 'utf8mb4');

// Create database connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error
            error_log("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    return $conn;
}

// Check if database exists, if not create it
function initializeDatabase() {
    try {
        // Connect without database name
        $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $conn->exec($sql);
        
        // Use the database
        $conn->exec("USE " . DB_NAME);
        
        // Read and execute SQL file
        $sqlFile = __DIR__ . '/../database/agent_system.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Split by delimiter and execute
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && substr($statement, 0, 2) != '--') {
                    try {
                        $conn->exec($statement);
                    } catch (PDOException $e) {
                        // Skip if already exists
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            error_log("SQL Error: " . $e->getMessage());
                        }
                    }
                }
            }
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

// Test database connection
function testDBConnection() {
    $conn = getDBConnection();
    if ($conn) {
        return ['success' => true, 'message' => 'Database connected successfully'];
    } else {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
}
