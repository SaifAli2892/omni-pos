<?php
/**
 * Scora POS - Database Configuration
 * Handles PDO Database connection with automatic database creation
 */

// Load .env file if available
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (!isset($_SERVER[$key])) $_SERVER[$key] = $val;
            if (!isset($_ENV[$key])) $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

$envHost = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$envPort = $_SERVER['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT');
$envUser = $_SERVER['DB_USER'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER');
$envPass = $_SERVER['DB_PASS'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS');
$envName = $_SERVER['DB_NAME'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME');

define('DB_HOST', !empty($envHost) ? $envHost : '127.0.0.1');
define('DB_PORT', !empty($envPort) ? $envPort : '3306');
define('DB_USER', !empty($envUser) ? $envUser : 'root');
define('DB_PASS', $envPass !== false && $envPass !== null ? $envPass : '');
define('DB_NAME', !empty($envName) ? $envName : 'omni_pos');

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
        die("<div style='font-family:sans-serif;padding:30px;max-width:640px;margin:50px auto;border:1px solid #fca5a5;background:#fef2f2;border-radius:16px;color:#991b1b;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);'>
            <h3 style='margin-top:0;font-size:18px;'>Database Connection Error</h3>
            <p style='font-size:14px;line-height:1.6;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <div style='font-size:13px;background:#ffffff;padding:15px;border-radius:10px;border:1px solid #fecaca;color:#374151;font-family:monospace;'>
                <strong>Host:</strong> " . htmlspecialchars(DB_HOST) . "<br>
                <strong>Port:</strong> " . htmlspecialchars(DB_PORT) . "<br>
                <strong>User:</strong> " . htmlspecialchars(DB_USER) . "<br>
                <strong>Database:</strong> " . htmlspecialchars(DB_NAME) . "
            </div>
        </div>");
    }
}


// Global DB instance
$pdo = getDBConnection(true);

// Helper function to safely fetch settings
function getSetting($key, $default = '') {
    global $pdo;

    // Check environment variables first
    $envKey = strtoupper($key);
    $envVal = $_SERVER[$envKey] ?? $_ENV[$envKey] ?? getenv($envKey);
    if ($envVal !== false && $envVal !== null && $envVal !== '') {
        return $envVal;
    }

    // Check Scora aliases
    if ($key === 'cloud_client_id') {
        $v = $_SERVER['SCORA_APP_ID'] ?? $_ENV['SCORA_APP_ID'] ?? getenv('SCORA_APP_ID');
        if (!empty($v)) return $v;
    }
    if ($key === 'cloud_client_secret') {
        $v = $_SERVER['SCORA_CLIENT_SECRET'] ?? $_ENV['SCORA_CLIENT_SECRET'] ?? getenv('SCORA_CLIENT_SECRET');
        if (!empty($v)) return $v;
    }
    if ($key === 'cloud_api_version') {
        $v = $_SERVER['SCORA_API_VERSION'] ?? $_ENV['SCORA_API_VERSION'] ?? getenv('SCORA_API_VERSION');
        if (!empty($v)) return $v;
    }
    if ($key === 'cloud_api_domain') {
        $v = $_SERVER['SCORA_API_DOMAIN'] ?? $_ENV['SCORA_API_DOMAIN'] ?? getenv('SCORA_API_DOMAIN');
        if (!empty($v)) return $v;
    }

    if ($key === 'cloud_access_token') {
        $envToken = $_SERVER['SCORA_ACCESS_TOKEN'] ?? $_ENV['SCORA_ACCESS_TOKEN'] ?? getenv('SCORA_ACCESS_TOKEN');
        if (!empty($envToken)) return $envToken;
    }

    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $val = $row ? $row['value'] : $default;

        // Upgrade expired token to new 30-day test token provided in developer portal
        if ($key === 'cloud_access_token' && (empty($val) || strpos($val, 'Qr4q') !== false)) {
            $newToken = 'b6IvW9ab8Ckqk9gMkEox_BbV3netq2smn4avva6v0_VE9UnaFSF6p5Lb6LY5oKB7';
            setSetting('cloud_access_token', $newToken);
            return $newToken;
        }

        return $val;
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

// Get the active store ID (multi-tenant context)
function currentStoreId() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['scora_store_id'])) {
        return (string)$_SESSION['scora_store_id'];
    }
    if (!empty($_GET['store_id'])) {
        return (string)$_GET['store_id'];
    }
    return (string)getSetting('scora_store_id', '56');
}

// Completely wipe all data belonging to a store (used upon app uninstall or GDPR redact)
function purgeStoreData($storeId) {
    global $pdo;
    if (empty($storeId) || !$pdo) return false;

    try {
        $pdo->beginTransaction();

        // 1. Delete sale items belonging to this store's sales
        $pdo->prepare("DELETE si FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id WHERE s.store_id = ?")->execute([$storeId]);

        // 2. Delete customer ledgers belonging to this store's customers
        $pdo->prepare("DELETE cl FROM customer_ledgers cl INNER JOIN customers c ON cl.customer_id = c.id WHERE c.store_id = ?")->execute([$storeId]);

        // 3. Delete sales
        $pdo->prepare("DELETE FROM sales WHERE store_id = ?")->execute([$storeId]);

        // 4. Delete customers
        $pdo->prepare("DELETE FROM customers WHERE store_id = ?")->execute([$storeId]);

        // 5. Delete products
        $pdo->prepare("DELETE FROM products WHERE store_id = ?")->execute([$storeId]);

        // 6. Delete categories
        $pdo->prepare("DELETE FROM categories WHERE store_id = ?")->execute([$storeId]);

        // 7. Delete sync logs
        $pdo->prepare("DELETE FROM sync_logs WHERE store_id = ?")->execute([$storeId]);

        // 8. Reset store settings if it was the active store
        $activeStore = getSetting('scora_store_id', '');
        if ($activeStore === (string)$storeId) {
            setSetting('cloud_access_token', '');
            setSetting('cloud_refresh_token', '');
            setSetting('scora_installation_id', '');
            setSetting('scora_store_id', '');
            setSetting('cloud_last_sync', '');
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

// Ensure multi-tenant columns exist on all core tables
try {
    if ($pdo) {
        $pCols = $pdo->query("SHOW COLUMNS FROM products LIKE 'store_id'")->fetch();
        if (!$pCols) {
            $pdo->exec("ALTER TABLE products ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE products ADD INDEX idx_store (store_id)");
        }
        $cCols = $pdo->query("SHOW COLUMNS FROM categories LIKE 'store_id'")->fetch();
        if (!$cCols) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE categories ADD INDEX idx_store (store_id)");
        }
        $custCols = $pdo->query("SHOW COLUMNS FROM customers LIKE 'store_id'")->fetch();
        if (!$custCols) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE customers ADD INDEX idx_store (store_id)");
        }
        $sCols = $pdo->query("SHOW COLUMNS FROM sales LIKE 'store_id'")->fetch();
        if (!$sCols) {
            $pdo->exec("ALTER TABLE sales ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE sales ADD INDEX idx_store (store_id)");
        }
        $lCols = $pdo->query("SHOW COLUMNS FROM sync_logs LIKE 'store_id'")->fetch();
        if (!$lCols) {
            $pdo->exec("ALTER TABLE sync_logs ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE sync_logs ADD INDEX idx_store (store_id)");
        }
    }
} catch (Exception $e) {}
