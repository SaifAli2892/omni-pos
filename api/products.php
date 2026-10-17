<?php
/**
 * Scora POS - Products API
 * Search, filter, and barcode lookup
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

$action = $_GET['action'] ?? 'list';
$storeId = (string)($_GET['store_id'] ?? currentStoreId());

if ($action === 'list') {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.store_id = ? AND p.is_active = 1 ORDER BY p.id ASC");
        $stmt->execute([$storeId]);
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'search_or_scan') {
    $q = trim($_GET['q'] ?? '');
    if (empty($q)) {
        echo json_encode(['success' => true, 'products' => []]);
        exit;
    }

    try {
        // First try exact barcode match
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.barcode = ? AND p.is_active = 1 LIMIT 1");
        $stmt->execute([$q]);
        $exactProduct = $stmt->fetch();

        if ($exactProduct) {
            echo json_encode([
                'success' => true,
                'exact_barcode' => true,
                'product' => $exactProduct
            ]);
            exit;
        }

        // Otherwise fuzzy search by title, SKU, or partial barcode
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE (p.title LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?) AND p.is_active = 1
            LIMIT 20
        ");
        $param = "%{$q}%";
        $stmt->execute([$param, $param, $param]);
        $products = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'exact_barcode' => false,
            'products' => $products
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'by_category') {
    $catId = (int)($_GET['category_id'] ?? 0);
    try {
        if ($catId > 0) {
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.is_active = 1 ORDER BY p.title ASC");
            $stmt->execute([$catId]);
        } else {
            $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.title ASC");
        }
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
