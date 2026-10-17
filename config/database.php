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

// Multi-tenant token isolation: Get token strictly for specific store
function getStoreAccessToken($storeId = null) {
    global $pdo;
    $storeId = (string)($storeId ?: currentStoreId());
    if (!$pdo || empty($storeId)) return null;

    try {
        $stmt = $pdo->prepare("SELECT access_token FROM store_tokens WHERE store_id = ? LIMIT 1");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['access_token'])) {
            return $row['access_token'];
        }
    } catch (Exception $e) {}

    return null;
}

// Get the actual store name for a store
function getStoreName($storeId = null) {
    global $pdo;
    $storeId = (string)($storeId ?: currentStoreId());
    if (!$pdo || empty($storeId)) {
        return $_SESSION['scora_store_name'] ?? 'My Store';
    }
    try {
        $stmt = $pdo->prepare("SELECT store_name FROM store_tokens WHERE store_id = ? LIMIT 1");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['store_name'])) {
            return $row['store_name'];
        }
    } catch (Exception $e) {}
}

// Get the store currency symbol
function getStoreCurrencySymbol($storeId = null) {
    global $pdo;
    $storeId = (string)($storeId ?: currentStoreId());
    if ($pdo && !empty($storeId)) {
        try {
            $stmt = $pdo->prepare("SELECT currency_symbol FROM store_tokens WHERE store_id = ? LIMIT 1");
            $stmt->execute([$storeId]);
            $sym = $stmt->fetchColumn();
            if (!empty($sym)) return $sym;
        } catch (Exception $e) {}
    }
    return $_SESSION['currency_symbol'] ?? getSetting('currency_symbol', '$');
}

// Save isolated token strictly for a store
function setStoreAccessToken($storeId, $accessToken, $refreshToken = null, $storeName = null, $storeDomain = null) {
    global $pdo;
    $storeId = (string)($storeId ?: currentStoreId());
    if (!$pdo || empty($storeId) || empty($accessToken)) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO store_tokens (store_id, store_name, store_domain, access_token, refresh_token)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                store_name = COALESCE(VALUES(store_name), store_name),
                store_domain = COALESCE(VALUES(store_domain), store_domain),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$storeId, $storeName, $storeDomain, $accessToken, $refreshToken]);
    } catch (Exception $e) {
        return false;
    }
}

function removeStoreToken($storeId) {
    global $pdo;
    if (!$pdo || empty($storeId)) return false;
    try {
        $stmt = $pdo->prepare("DELETE FROM store_tokens WHERE store_id = ?");
        return $stmt->execute([(string)$storeId]);
    } catch (Exception $e) {
        return false;
    }
}

// Robust parser for Scora embedded session token (JWT)
function parseScoraContext($raw) {
    if (empty($raw) || !is_string($raw)) return [];
    $parts = explode('.', $raw);
    $data = [];
    foreach ($parts as $part) {
        $clean = strtr($part, '-_', '+/');
        $pad = strlen($clean) % 4;
        if ($pad > 0) $clean .= str_repeat('=', 4 - $pad);
        $decoded = @base64_decode($clean);
        if ($decoded !== false) {
            $json = @json_decode($decoded, true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }
    }
    return $data;
}

// Get the active store ID (multi-tenant context)
function currentStoreId() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // 1. Direct query parameter
    if (!empty($_GET['store_id'])) {
        $sid = (string)$_GET['store_id'];
        $_SESSION['scora_store_id'] = $sid;
        return $sid;
    }

    // 2. Scora Embedded Context (JWT)
    if (!empty($_GET['scora_context'])) {
        $ctx = parseScoraContext($_GET['scora_context']);
        $candidateId = $ctx['store_id'] ?? $ctx['shop_id'] ?? null;
        if (!$candidateId && !empty($ctx['dest'])) {
            if (preg_match('/\/(\d+)$/', $ctx['dest'], $m)) $candidateId = $m[1];
        }
        if (!$candidateId && !empty($ctx['sub'])) {
            if (preg_match('/\/(\d+)$/', $ctx['sub'], $m)) $candidateId = $m[1];
        }
        if (!empty($candidateId)) {
            $sid = (string)$candidateId;
            $_SESSION['scora_store_id'] = $sid;
            if (!empty($ctx['store_name']) || !empty($ctx['shop_name'])) {
                $_SESSION['scora_store_name'] = $ctx['store_name'] ?? $ctx['shop_name'];
            }
            if (!empty($ctx['installation_id'])) {
                $_SESSION['scora_installation_id'] = (string)$ctx['installation_id'];
                setSetting('scora_installation_id', (string)$ctx['installation_id']);
            }
            return $sid;
        }
    }

    // 3. Active session store
    if (!empty($_SESSION['scora_store_id'])) {
        return (string)$_SESSION['scora_store_id'];
    }

    // 4. Stored setting if available
    $savedStore = (string)getSetting('scora_store_id', '');
    return $savedStore;
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

        // 8. Delete store credentials/tokens
        $pdo->prepare("DELETE FROM store_tokens WHERE store_id = ?")->execute([$storeId]);

        // 9. Reset store settings if it was the active store
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

// Ensure multi-tenant columns & tables exist on all core entities
try {
    if ($pdo) {
        // Multi-tenant isolated store tokens table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS store_tokens (
                store_id VARCHAR(50) PRIMARY KEY,
                store_name VARCHAR(100) NULL,
                store_domain VARCHAR(150) NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT NULL,
                currency VARCHAR(10) NULL DEFAULT 'USD',
                currency_symbol VARCHAR(10) NULL DEFAULT '$',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stCur = $pdo->query("SHOW COLUMNS FROM store_tokens LIKE 'currency'")->fetch();
        if (!$stCur) {
            $pdo->exec("ALTER TABLE store_tokens ADD COLUMN currency VARCHAR(10) NULL DEFAULT 'USD', ADD COLUMN currency_symbol VARCHAR(10) NULL DEFAULT '$'");
        }

        $pCols = $pdo->query("SHOW COLUMNS FROM products LIKE 'store_id'")->fetch();
        if (!$pCols) {
            $pdo->exec("ALTER TABLE products ADD COLUMN store_id VARCHAR(50) NOT NULL DEFAULT '56' AFTER id");
            $pdo->exec("ALTER TABLE products ADD INDEX idx_store (store_id)");
        }
        $invCol = $pdo->query("SHOW COLUMNS FROM products LIKE 'scora_inventory_item_id'")->fetch();
        if (!$invCol) {
            $pdo->exec("ALTER TABLE products ADD COLUMN scora_inventory_item_id VARCHAR(100) NULL AFTER scora_variant_id");
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

        // Purge all cross-pollinated test shampoo products from all stores
        $pdo->exec("DELETE FROM products WHERE title LIKE 'LEO Organic%' OR title LIKE '%hair and skin care%' OR title LIKE 'Haircare package' OR title LIKE 'skin care package'");

        // Delete global fallback access token and store id from settings so OAuth is strictly per-store
        $pdo->exec("DELETE FROM settings WHERE `key` IN ('cloud_access_token', 'scora_store_id')");
    }
} catch (Exception $e) {}
