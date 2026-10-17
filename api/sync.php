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

if ($action === 'introspect') {
    $q = 'query {
        __type(name: "Product") {
            fields {
                name
                type { name kind ofType { name kind } }
            }
        }
        mutationType: __schema {
            mutationType {
                fields {
                    name
                }
            }
        }
    }';
    $res = $client->query($q);
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

echo json_encode(['success' => false, 'error' => 'Invalid action']);
