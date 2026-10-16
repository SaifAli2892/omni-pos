<?php
/**
 * Scora POS - Database Configuration
 * Handles PDO Database connection with automatic database creation
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'omni_pos');


function getDBConnection($includeDb = true) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        if ($includeDb) {
            $dsn .= ";dbname=" . DB_NAME;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        if (!$includeDb) {
            die("Database server connection failed: " . $e->getMessage());
        }
        // If DB doesn't exist yet, we will try to connect without DB to allow installer
        return false;
    }
}

// Global DB instance
$pdo = getDBConnection(true);

// Helper function to safely fetch settings
function getSetting($key, $default = '') {
    global $pdo;
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper function to update/save setting
function setSetting($key, $value) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}
