<?php
/**
 * OmniPOS - Inbound Cloud Webhook Handler
 * Listens for online store orders and synchronizes stock locally in real-time
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawPayload = file_get_contents('php://input');
$client = new CloudSyncClient($pdo);

// Scora HMAC-SHA256 Signature Verification
$clientSecret = getSetting('cloud_client_secret', '9fbddbbdeb2f98337ef2b94513af72a0945eaa1a5bcfba5f95437875842de1f9');
$scoraSignature = $_SERVER['HTTP_X_SCORA_HMAC_SHA256'] ?? $_SERVER['HTTP_X_SCORA_SIGNATURE'] ?? '';

if (!empty($scoraSignature) && !empty($clientSecret) && !empty($rawPayload)) {
    $calcBase64 = base64_encode(hash_hmac('sha256', $rawPayload, $clientSecret, true));
    $calcHex    = hash_hmac('sha256', $rawPayload, $clientSecret);
    if (!hash_equals($calcBase64, $scoraSignature) && !hash_equals($calcHex, $scoraSignature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid webhook signature']);
        exit;
    }
}

$data = json_decode($rawPayload, true);

// Scora Challenge / Webhook Verification Handshake
if (isset($data['type']) && $data['type'] === 'scora.gateway_webhook.verify') {
    $challenge = $data['challenge'] ?? '';
    $timestamp = $data['timestamp'] ?? time();
    echo json_encode([
        'challenge' => $challenge,
        'timestamp' => $timestamp
    ]);
    exit;
}

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    // If webhook contains line items (e.g., order created online)
    if (!empty($data['lineItems']) || !empty($data['line_items'])) {
        $items = $data['lineItems'] ?? $data['line_items'];
        $deductStmt = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE scora_product_id = ? OR scora_variant_id = ?");

        foreach ($items as $item) {
            $qty = (int)($item['quantity'] ?? 1);
            $varId = $item['variantId'] ?? $item['variant_id'] ?? null;
            $prodId = $item['productId'] ?? $item['product_id'] ?? null;

            if ($varId || $prodId) {
                $deductStmt->execute([$qty, $prodId, $varId]);
            }
        }

        $orderId = $data['id'] ?? $data['order_number'] ?? 'Online Order';
        $client->logEvent('webhook_order', "Online order {$orderId} received. Stock deducted in local POS.", 'success');
    }

    echo json_encode(['status' => 'received']);

} catch (Exception $e) {
    $client->logEvent('webhook_error', $e->getMessage(), 'error');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
