<?php
/**
 * OmniPOS - Store App Uninstallation & Complete Data Wipe Endpoint
 * Handles app uninstalls from Scora or store admin
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sync.php';

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true) ?: $_POST;

$storeId = (string)($data['store_id'] ?? $data['shop_id'] ?? $_GET['store_id'] ?? currentStoreId());

if (empty($storeId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Store ID is required for uninstallation.']);
    exit;
}

$success = purgeStoreData($storeId);

if ($success) {
    echo json_encode([
        'success'  => true,
        'purged'   => true,
        'store_id' => $storeId,
        'message'  => "OmniPOS uninstalled successfully. All catalog items, customer records, and sales for store {$storeId} have been permanently deleted."
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to wipe store data.']);
}
