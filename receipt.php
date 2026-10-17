<?php
/**
 * OmniPOS - Thermal Receipt Renderer
 * Standalone & JSON view for thermal receipts
 */

require_once __DIR__ . '/config/database.php';

$saleId = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'html';

if ($saleId <= 0) {
    die("Invalid Sale ID");
}

try {
    $sStmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.cashier_id = u.id
        WHERE s.id = ?
    ");
    $sStmt->execute([$saleId]);
    $sale = $sStmt->fetch();

    if (!$sale) {
        if ($format === 'json') {
            echo json_encode(['success' => false, 'error' => 'Sale not found']);
            exit;
        }
        die("Sale not found");
    }

    $iStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $iStmt->execute([$saleId]);
    $items = $iStmt->fetchAll();

    $storeId     = !empty($sale['store_id']) ? $sale['store_id'] : currentStoreId();
    $currency    = getStoreCurrencySymbol($storeId);
    $storeName   = getStoreName($storeId) ?: getSetting('store_name', 'Omni Retail Mart');
    $storePhone  = getSetting('store_phone', '0300-1234567');
    $storeAddress= getSetting('store_address', 'Lahore, Pakistan');
    $footerText  = getSetting('receipt_footer', 'Thank you for shopping with us!');

    $isDec = ($currency !== 'Rs.');
    $fmt = function($num) use ($isDec) {
        return $isDec ? number_format((float)$num, 2) : number_format((float)$num);
    };

    ob_start();
    ?>
    <div style="text-align: center; margin-bottom: 8px;">
        <h2 style="margin: 0; font-size: 16px; font-weight: bold;"><?php echo htmlspecialchars($storeName); ?></h2>
        <p style="margin: 2px 0; font-size: 10px;"><?php echo htmlspecialchars($storeAddress); ?></p>
        <p style="margin: 2px 0; font-size: 10px;">Phone: <?php echo htmlspecialchars($storePhone); ?></p>
        <p style="margin: 4px 0; border-bottom: 1px dashed #000;"></p>
    </div>

    <div style="font-size: 10px; margin-bottom: 6px;">
        <div><b>Invoice:</b> <?php echo htmlspecialchars($sale['invoice_no']); ?></div>
        <div><b>Date:</b> <?php echo date('d-M-Y h:i A', strtotime($sale['created_at'])); ?></div>
        <div><b>Customer:</b> <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?></div>
        <div><b>Cashier:</b> <?php echo htmlspecialchars($sale['cashier_name'] ?: 'Admin'); ?></div>
        <div><b>Payment:</b> <?php echo strtoupper($sale['payment_method']); ?></div>
        <?php if ($sale['scora_sync_status'] === 'synced'): ?>
            <div><b>Cloud Sync:</b> Verified ✓</div>
        <?php endif; ?>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 8px;">
        <thead>
            <tr style="border-bottom: 1px dashed #000; border-top: 1px dashed #000;">
                <th style="text-align: left; padding: 3px 0;">Item</th>
                <th style="text-align: right; padding: 3px 0;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="padding: 2px 0;">
                        <?php echo htmlspecialchars($item['product_title']); ?><br>
                        <small><?php echo $item['quantity']; ?> × <?php echo $currency . ' ' . $fmt($item['unit_price']); ?></small>
                    </td>
                    <td style="text-align: right; vertical-align: bottom;">
                        <?php echo $currency . ' ' . $fmt($item['subtotal']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="border-top: 1px dashed #000; padding-top: 4px; font-size: 11px;">
        <div style="display: flex; justify-content: space-between;">
            <span>Subtotal:</span>
            <span><?php echo $currency . ' ' . $fmt($sale['subtotal']); ?></span>
        </div>
        <?php if ((float)$sale['discount'] > 0): ?>
            <div style="display: flex; justify-content: space-between;">
                <span>Discount:</span>
                <span>-<?php echo $currency . ' ' . $fmt($sale['discount']); ?></span>
            </div>
        <?php endif; ?>
        <?php if ((float)$sale['tax_amount'] > 0): ?>
            <div style="display: flex; justify-content: space-between;">
                <span>Tax / GST:</span>
                <span>+<?php echo $currency . ' ' . $fmt($sale['tax_amount']); ?></span>
            </div>
        <?php endif; ?>
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; margin: 4px 0;">
            <span>TOTAL:</span>
            <span><?php echo $currency . ' ' . $fmt($sale['total_amount']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 10px;">
            <span>Paid:</span>
            <span><?php echo $currency . ' ' . $fmt($sale['paid_amount']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 10px;">
            <span>Change Due:</span>
            <span><?php echo $currency . ' ' . $fmt($sale['change_amount']); ?></span>
        </div>
    </div>

    <div style="text-align: center; margin-top: 12px; font-size: 10px; border-top: 1px dashed #000; padding-top: 6px;">
        <p style="margin: 2px 0;"><?php echo htmlspecialchars($footerText); ?></p>
        <p style="margin: 2px 0; font-size: 9px; color: #555;">Powered by OmniPOS</p>
    </div>
    <?php
    $html = ob_get_clean();

    if ($format === 'json') {
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }

    // Direct standalone printable HTML view
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Receipt #<?php echo htmlspecialchars($sale['invoice_no']); ?></title>
        <style>
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                font-family: 'JetBrains Mono', 'Courier New', Courier, monospace;
                font-size: 12px;
                line-height: 1.35;
                color: #000;
                background: #fff;
                width: 76mm;
                margin: 0 auto;
                padding: 6mm 4mm;
                box-sizing: border-box;
            }
            table { width: 100%; border-collapse: collapse; }
            th, td { font-size: 11px; }
            .no-print {
                margin-bottom: 14px;
                display: flex;
                gap: 8px;
            }
            .no-print button {
                flex: 1;
                padding: 10px 14px;
                font-size: 12px;
                font-weight: bold;
                border-radius: 8px;
                border: 1px solid #cbd5e1;
                cursor: pointer;
                background: #f8fafc;
                font-family: sans-serif;
            }
            .no-print .btn-print {
                background: #0f172a;
                color: #ffffff;
                border-color: #0f172a;
            }
            .no-print .btn-print:hover {
                background: #1e293b;
            }
            @media print {
                .no-print { display: none !important; }
                body { padding: 4mm 2mm !important; margin: 0 !important; width: 80mm !important; }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button type="button" class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
            <button type="button" onclick="window.close()">✕ Close Window</button>
        </div>
        <?php echo $html; ?>
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.focus();
                    window.print();
                }, 300);
            });
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    if ($format === 'json') {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    die("Receipt error: " . $e->getMessage());
}
