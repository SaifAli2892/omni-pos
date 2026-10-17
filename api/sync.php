<?php
/**
 * OmniPOS - Cloud Store Auto-Sync API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sync.php';

$action = $_GET['action'] ?? '';
$client = new CloudSyncClient($pdo);

if ($action === 'auto_connect_store') {
    $input = json_decode(file_get_contents('php://input'), true);
    $storeName   = trim($input['store_name'] ?? '');
    $storeDomain = trim($input['store_domain'] ?? '');

    if (empty($storeName) && empty($storeDomain)) {
        echo json_encode(['success' => false, 'error' => 'Please enter your store name or domain.']);
        exit;
    }

    if (empty($storeDomain)) {
        $storeDomain = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $storeName)) . '.scora.com.pk';
    }
    if (empty($storeName)) {
        $storeName = ucfirst(explode('.', $storeDomain)[0]);
    }

    $res = $client->autoSyncStore($storeName, $storeDomain);
    echo json_encode($res);
    exit;
}


if ($action === 'test_connection') {
    $res = $client->testConnection();
    echo json_encode($res);
    exit;
}

if ($action === 'import_catalog') {
    $storeId = $_GET['store_id'] ?? currentStoreId();
    $res = $client->importStoreProducts($storeId);
    echo json_encode($res);
    exit;
}

if ($action === 'save_token') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $targetStoreId = (string)($input['store_id'] ?? currentStoreId());

    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Please provide an access token.']);
        exit;
    }

    $shopInfo = $client->detectShopDetails($token);
    if (!$shopInfo) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired Scora token. Could not authenticate with Scora GraphQL API.'
        ]);
        exit;
    }

    $detectedStoreId   = $shopInfo['store_id'];
    $detectedStoreName = $shopInfo['store_name'];

    // Save token strictly to detected store
    setStoreAccessToken($detectedStoreId, $token, null, $detectedStoreName);
    setSetting('scora_store_id', $detectedStoreId);
    setSetting('store_name', $detectedStoreName);

    // Sync products for detected store
    $importRes = $client->importStoreProducts($detectedStoreId);

    $warning = '';
    if (!empty($targetStoreId) && $targetStoreId !== $detectedStoreId) {
        $warning = " (Note: This token is for '{$detectedStoreName}' Store {$detectedStoreId}. In Scora Developer Portal, select 'STORE CONTEXT' as your desired store to get its token).";
    }

    echo json_encode([
        'success'    => true,
        'store_id'   => $detectedStoreId,
        'store_name' => $detectedStoreName,
        'imported'   => $importRes['imported'] ?? 0,
        'message'    => "Connected to {$detectedStoreName}! Imported " . ($importRes['imported'] ?? 0) . " live products." . $warning
    ]);
    exit;
}

if ($action === 'cleanup_duplicates') {
    $res = $client->cleanupDuplicates();
    echo json_encode($res);
    exit;
}

if ($action === 'wipe_store') {
    $storeId = (string)($_GET['store_id'] ?? currentStoreId());
    if (empty($storeId)) {
        echo json_encode(['success' => false, 'error' => 'No store selected']);
        exit;
    }
    $purged = purgeStoreData($storeId);
    echo json_encode([
        'success'  => $purged,
        'store_id' => $storeId,
        'message'  => "Store {$storeId} data, products, orders, and token completely wiped."
    ]);
    exit;
}

if ($action === 'debug') {
    $sStmt = $pdo->query("SELECT `key`, `value` FROM settings");
    $settings = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $lStmt = $pdo->query("SELECT * FROM sync_logs ORDER BY id DESC LIMIT 15");
    $logs = $lStmt->fetchAll();
    $pCount = $pdo->query("SELECT count(*) FROM products")->fetchColumn();
    echo json_encode([
        'settings' => $settings,
        'logs' => $logs,
        'product_count' => $pCount
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
