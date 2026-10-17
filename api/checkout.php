<?php
/**
 * OmniPOS - Checkout & Sale Processing API
 * Handles transaction, inventory deduction, Khata update, and real-time cloud sync
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
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
    exit;
}

$storeId       = (string)($input['store_id'] ?? $_GET['store_id'] ?? currentStoreId());
$customerId    = !empty($input['customer_id']) ? (int)$input['customer_id'] : 1; // 1 = Walk-in
$paymentMethod = $input['payment_method'] ?? 'cash';
$subtotal      = (float)($input['subtotal'] ?? 0);
$discount      = (float)($input['discount'] ?? 0);
$taxAmount     = (float)($input['tax_amount'] ?? 0);
$totalAmount   = (float)($input['total_amount'] ?? 0);
$paidAmount    = (float)($input['paid_amount'] ?? $totalAmount);
$changeAmount  = max(0, $paidAmount - $totalAmount);
$items         = $input['items'];
$notes         = trim($input['notes'] ?? '');
$user          = currentUser();
$cashierId     = $user['id'];

// Generate unique invoice number: INV-YYYYMMDD-XXXX
$invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

try {
    $pdo->beginTransaction();

    // 1. Insert into sales table
    $stmt = $pdo->prepare("
        INSERT INTO sales (
            store_id, invoice_no, customer_id, cashier_id, subtotal, discount,
            tax_amount, total_amount, paid_amount, change_amount,
            payment_method, status, scora_sync_status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'pending', ?)
    ");
    $stmt->execute([
        $storeId, $invoiceNo, $customerId, $cashierId, $subtotal, $discount,
        $taxAmount, $totalAmount, $paidAmount, $changeAmount,
        $paymentMethod, $notes
    ]);
    $saleId = $pdo->lastInsertId();

    // 2. Insert items and deduct local inventory
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO sale_items (sale_id, product_id, product_title, cost_price, unit_price, quantity, subtotal, scora_product_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stockDeductStmt = $pdo->prepare("
        UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?
    ");

    $cloudItemsToSync = [];

    foreach ($items as $item) {
        $productId = (int)$item['id'];
        $qty       = (int)$item['qty'];
        $unitPrice = (float)$item['price'];
        $lineTotal = (float)($unitPrice * $qty);
        $costPrice = (float)($item['cost_price'] ?? 0);
        $title     = $item['title'];
        $cloudId   = $item['scora_product_id'] ?? null;
        $cloudVarId= $item['scora_variant_id'] ?? null;

        $itemInsertStmt->execute([
            $saleId, $productId, $title, $costPrice, $unitPrice, $qty, $lineTotal, $cloudId
        ]);

        $stockDeductStmt->execute([$qty, $productId]);

        if (!empty($cloudId) || !empty($cloudVarId)) {
            $cloudItemsToSync[] = [
                'product_id' => $cloudId,
                'variant_id' => $cloudVarId,
                'delta'      => -$qty
            ];
        }
    }

    // 3. Handle Khata / Udhaar if payment method is udhaar
    if ($paymentMethod === 'udhaar') {
        $creditAmount = $totalAmount;
        $custStmt = $pdo->prepare("SELECT credit_balance FROM customers WHERE id = ?");
        $custStmt->execute([$customerId]);
        $cust = $custStmt->fetch();
        $newBalance = ($cust ? (float)$cust['credit_balance'] : 0) + $creditAmount;

        // Update customer balance
        $updateCust = $pdo->prepare("UPDATE customers SET credit_balance = ? WHERE id = ?");
        $updateCust->execute([$newBalance, $customerId]);

        // Insert into customer ledger
        $ledgerStmt = $pdo->prepare("
            INSERT INTO customer_ledgers (customer_id, sale_id, type, amount, balance_after, notes)
            VALUES (?, ?, 'credit_sale', ?, ?, ?)
        ");
        $ledgerStmt->execute([$customerId, $saleId, $creditAmount, $newBalance, "Sale {$invoiceNo} on Udhaar/Credit"]);
    }

    $pdo->commit();

    // 4. Background Real-time Cloud API Synchronization
    $cloudSyncResult = 'local_only';
    $syncClient = new CloudSyncClient($pdo, $storeId);
    if (!empty($cloudItemsToSync) && !empty($syncClient->getAccessToken())) {
        $syncSuccess = true;
        $syncErrors = [];
        foreach ($cloudItemsToSync as $sItem) {
            $res = $syncClient->adjustInventory($sItem['product_id'], $sItem['variant_id'], $sItem['delta']);
            if (empty($res['success'])) {
                $syncSuccess = false;
                $syncErrors[] = $res['error'] ?? 'Stock deduction failed';
            }
        }
        $cloudSyncResult = $syncSuccess ? 'synced' : 'failed';
        $updateSync = $pdo->prepare("UPDATE sales SET scora_sync_status = ? WHERE id = ?");
        $updateSync->execute([$cloudSyncResult, $saleId]);
        if (!$syncSuccess) {
            $syncClient->logEvent('stock_sync_error', "Stock sync error on invoice {$invoiceNo}: " . implode('; ', $syncErrors), 'error');
        } else {
            $syncClient->logEvent('stock_sync_success', "Successfully deducted online stock for invoice {$invoiceNo}", 'success');
        }
    }

    // 5. Fetch Customer Details for Receipt
    $custName = 'Walk-in Customer';
    if ($customerId > 1) {
        $cStmt = $pdo->prepare("SELECT name, phone FROM customers WHERE id = ?");
        $cStmt->execute([$customerId]);
        $cRow = $cStmt->fetch();
        if ($cRow) $custName = $cRow['name'];
    }

    echo json_encode([
        'success'           => true,
        'sale_id'           => $saleId,
        'invoice_no'        => $invoiceNo,
        'total_amount'      => $totalAmount,
        'paid_amount'       => $paidAmount,
        'change_amount'     => $changeAmount,
        'payment_method'    => $paymentMethod,
        'customer_name'     => $custName,
        'sync_status'       => $cloudSyncResult,
        'created_at'        => date('d-M-Y h:i A')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Checkout failed: ' . $e->getMessage()]);
}
