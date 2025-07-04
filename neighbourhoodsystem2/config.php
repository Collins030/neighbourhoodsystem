<?php
// config.php - Database configuration and connection

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'neighbourhood_system');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Session configuration
session_start();

// Helper function to generate secure random token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to create user session
function createUserSession($userId) {
    $pdo = getDBConnection();
    $token = generateSecureToken(64);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);
    
    setcookie('session_token', $token, strtotime('+30 days'), '/', '', false, true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['session_token'] = $token;
    
    return $token;
}

// Function to verify user session
function verifyUserSession() {
    if (!isset($_COOKIE['session_token']) && !isset($_SESSION['session_token'])) {
        return false;
    }
    
    $token = $_COOKIE['session_token'] ?? $_SESSION['session_token'];
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.full_name 
        FROM users u 
        JOIN user_sessions s ON u.id = s.user_id 
        WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1
    ");
    $stmt->execute([$token]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to destroy user session
function destroyUserSession() {
    if (isset($_COOKIE['session_token'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_COOKIE['session_token']]);
        
        setcookie('session_token', '', time() - 3600, '/');
    }
    
    session_destroy();
}

// Clean up expired sessions
function cleanupExpiredSessions() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    $stmt->execute();
}

// Run cleanup occasionally
if (rand(1, 100) == 1) {
    cleanupExpiredSessions();
}
?>