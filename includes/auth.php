<?php
/**
 * Scora POS - Authentication and Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Auto-login default user for frictionless initial demo if not set
if (!isset($_SESSION['user_id'])) {
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
            $defaultUser = $stmt->fetch();
            if ($defaultUser) {
                $_SESSION['user_id'] = $defaultUser['id'];
                $_SESSION['user_name'] = $defaultUser['name'];
                $_SESSION['user_role'] = $defaultUser['role'];
            }
        } catch (Exception $e) {}
    }
}

function currentUser() {
    return [
        'id'   => $_SESSION['user_id'] ?? 1,
        'name' => $_SESSION['user_name'] ?? 'Admin User',
        'role' => $_SESSION['user_role'] ?? 'admin'
    ];
}

function isAdmin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}
