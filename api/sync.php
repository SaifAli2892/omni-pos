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
    $res = $client->importStoreProducts();
    echo json_encode($res);
    exit;
}

if ($action === 'cleanup_duplicates') {
    $res = $client->cleanupDuplicates();
    echo json_encode($res);
    exit;
}

if ($action === 'debug') {
    $sStmt = $pdo->query("SELECT * FROM settings");
    $settings = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $lStmt = $pdo->query("SELECT * FROM sync_logs ORDER BY id DESC LIMIT 10");
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
