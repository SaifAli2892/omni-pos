<?php
/**
 * OmniPOS - Cloud Store Auto-Sync API
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sync.php';

$action = $_GET['action'] ?? '';
$storeId = (string)($_GET['store_id'] ?? currentStoreId());
$client = new CloudSyncClient($pdo, $storeId);

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

if ($action === 'test_query') {
    $q = $_POST['q'] ?? $_GET['q'] ?? 'query { products(first: 2) { nodes { id title } } }';
    $vars = !empty($_POST['variables']) ? (is_array($_POST['variables']) ? $_POST['variables'] : json_decode($_POST['variables'], true)) : [];
    $res = $client->query($q, $vars ?: []);
    echo json_encode($res);
    exit;
}

if ($action === 'debug') {
    $storeId = (string)($_GET['store_id'] ?? currentStoreId());
    $pStmt = $pdo->prepare("SELECT * FROM products WHERE store_id = ?");
    $pStmt->execute([$storeId]);
    $storeProducts = $pStmt->fetchAll();
    
    $rawGraphQL = $client->fetchProducts(10);

    echo json_encode([
        'store_id' => $storeId,
        'products_in_db' => $storeProducts,
        'raw_graphql' => $rawGraphQL
    ]);
    exit;
}

if ($action === 'inspect_scopes') {
    $token = $client->getAccessToken();
    $parts = explode('.', $token);
    $payload = null;
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }
    echo json_encode([
        'store_id' => $storeId,
        'has_token' => !empty($token),
        'jwt_payload' => $payload,
        'token_prefix' => substr($token, 0, 15) . '...'
    ]);
    exit;
}

if ($action === 'test_rest') {
    $targetUrl = $_POST['url'] ?? $_GET['url'] ?? '';
    $method = $_POST['method'] ?? 'GET';
    $postBody = $_POST['body'] ?? null;
    $token = $client->getAccessToken();

    $ch = curl_init($targetUrl);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 8
    ]);
    if ($postBody) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postBody) ? json_encode($postBody) : $postBody);
    }
    $resBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        'url' => $targetUrl,
        'http_code' => $httpCode,
        'response' => $resBody
    ]);
    exit;
}

if ($action === 'inspect_logs') {
    $now = date('Y-m-d H:i:s');
    $lastLogs = $pdo->query("SELECT * FROM sync_logs ORDER BY id DESC LIMIT 5")->fetchAll();
    echo json_encode(['server_now' => $now, 'last_logs' => $lastLogs]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
