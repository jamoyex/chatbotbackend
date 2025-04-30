<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_database_name');

// Chatbase configuration
define('CHATBASE_API_KEY', 'your_chatbase_api_key');
define('CHATBASE_BOT_ID', 'your_chatbase_bot_id');

// Admin credentials (username => password)
define('ADMIN_CREDENTIALS', [
    'admin' => 'your_secure_password',
    // Add more admin users as needed
]);

// Session configuration
session_start();

// Database connection
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Helper function to check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_username']) && isset(ADMIN_CREDENTIALS[$_SESSION['admin_username']]);
}

// Helper function to verify admin credentials
function verifyAdminCredentials($username, $password) {
    return isset(ADMIN_CREDENTIALS[$username]) && ADMIN_CREDENTIALS[$username] === $password;
}
?> 