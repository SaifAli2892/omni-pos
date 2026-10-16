<?php
/**
 * OmniPOS - Sales Registry & Invoices
 * Professional Clean White Theme (Full Screen)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

// Handle Void / Cancel Sale
$message = '';
$messageType = 'success';

if (isset($_POST['void_sale'])) {
    $saleId = (int)$_POST['sale_id'];
    try {
        $pdo->beginTransaction();

        $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $itemsStmt->execute([$saleId]);
        $saleItems = $itemsStmt->fetchAll();

        $restoreStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
        foreach ($saleItems as $it) {
            if (!empty($it['product_id'])) {
                $restoreStmt->execute([$it['quantity'], $it['product_id']]);
            }
        }

        $saleStmt = $pdo->prepare("SELECT customer_id, total_amount, payment_method, invoice_no FROM sales WHERE id = ?");
        $saleStmt->execute([$saleId]);
        $sRow = $saleStmt->fetch();

        if ($sRow && $sRow['payment_method'] === 'udhaar' && !empty($sRow['customer_id'])) {
            $reverseBalStmt = $pdo->prepare("UPDATE customers SET credit_balance = GREATEST(0, credit_balance - ?) WHERE id = ?");
            $reverseBalStmt->execute([(float)$sRow['total_amount'], $sRow['customer_id']]);

            $ledgStmt = $pdo->prepare("INSERT INTO customer_ledgers (customer_id, sale_id, type, amount, balance_after, notes) VALUES (?, ?, 'payment_received', ?, 0, ?)");
            $ledgStmt->execute([$sRow['customer_id'], $saleId, (float)$sRow['total_amount'], "Reversal of voided invoice {$sRow['invoice_no']}"]);
        }

        $voidStmt = $pdo->prepare("UPDATE sales SET status = 'voided' WHERE id = ?");
        $voidStmt->execute([$saleId]);

        $pdo->commit();
        $message = "Invoice #{$sRow['invoice_no']} voided and stock restored.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "Error voiding sale: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$method = trim($_GET['method'] ?? '');
$dateRange = trim($_GET['date'] ?? 'all');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(s.invoice_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (!empty($method)) {
    $where[] = "s.payment_method = ?";
    $params[] = $method;
}
if ($dateRange === 'today') {
    $where[] = "DATE(s.created_at) = CURDATE()";
} elseif ($dateRange === 'week') {
    $where[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateRange === 'month') {
    $where[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereClause = implode(" AND ", $where);

// Metrics
$metrics = [
    'total_revenue' => 0,
    'total_sales'   => 0,
    'udhaar_sales'  => 0
];
try {
    $mStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(id) as total_sales,
            COALESCE(SUM(CASE WHEN payment_method = 'udhaar' THEN total_amount ELSE 0 END), 0) as udhaar_sales
        FROM sales 
        WHERE status = 'completed' AND DATE(created_at) = CURDATE()
    ");
    $metrics = $mStmt->fetch() ?: $metrics;
} catch (Exception $e) {}

// Sales List
$salesList = [];
try {
    $sql = "
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.cashier_id = u.id
        WHERE {$whereClause}
        ORDER BY s.id DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $salesList = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Sales History & Invoices</h1>
            <p class="text-xs text-slate-500 mt-1">Review past transactions, reprint customer receipts, or void incorrect orders.</p>
        </div>
    </div>

    <!-- Quick Stats Cards (Clean White) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Today's Revenue</span>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1">
                <?php echo $currency; ?> <?php echo number_format((float)$metrics['total_revenue']); ?>
            </div>
            <span class="text-xs text-slate-500 font-medium"><?php echo $metrics['total_sales']; ?> orders completed today</span>
        </div>

        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Today's Udhaar (Credit)</span>
            <div class="text-2xl font-black text-amber-700 font-mono mt-1">
                <?php echo $currency; ?> <?php echo number_format((float)$metrics['udhaar_sales']); ?>
            </div>
            <span class="text-xs text-slate-500 font-medium">Added to customer khata</span>
        </div>

        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Average Order Value</span>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1">
                <?php 
                    $aov = $metrics['total_sales'] > 0 ? ($metrics['total_revenue'] / $metrics['total_sales']) : 0;
                    echo $currency . ' ' . number_format($aov);
                ?>
            </div>
            <span class="text-xs text-slate-500 font-medium">Per checkout ticket</span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl text-xs font-semibold flex items-center gap-2 <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Form -->
    <form method="GET" class="p-4 rounded-xl bg-white border border-slate-200 flex flex-wrap items-center justify-between gap-3 mb-6 shadow-sm">
        <div class="flex-1 min-w-[240px]">
            <input 
                type="text" 
                name="q" 
                value="<?php echo htmlspecialchars($search); ?>" 
                placeholder="Search by invoice # or customer name..." 
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600 font-medium"
            >
        </div>

        <div class="w-40">
            <select name="method" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-700 text-xs focus:outline-none font-medium">
                <option value="">All Payment Types</option>
                <option value="cash" <?php echo $method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="card" <?php echo $method === 'card' ? 'selected' : ''; ?>>Card</option>
                <option value="jazzcash" <?php echo $method === 'jazzcash' ? 'selected' : ''; ?>>JazzCash</option>
                <option value="easypaisa" <?php echo $method === 'easypaisa' ? 'selected' : ''; ?>>EasyPaisa</option>
                <option value="udhaar" <?php echo $method === 'udhaar' ? 'selected' : ''; ?>>Udhaar</option>
            </select>
        </div>

        <div class="w-40">
            <select name="date" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-700 text-xs focus:outline-none font-medium">
                <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>Past 7 Days</option>
                <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Past 30 Days</option>
            </select>
        </div>

        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition">
            Filter
        </button>
    </form>

    <!-- Full-Width Sales Table -->
    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold">
                        <th class="py-3.5 px-5">Invoice #</th>
                        <th class="py-3.5 px-5">Customer</th>
                        <th class="py-3.5 px-5">Payment Method</th>
                        <th class="py-3.5 px-5">Total Amount</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5">Date & Time</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($salesList)): ?>
                        <tr>
                            <td colspan="7" class="py-16 text-center text-slate-400 font-medium">
                                No sales transactions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($salesList as $sale): ?>
                            <?php $isVoid = ($sale['status'] === 'voided'); ?>
                            <tr class="hover:bg-slate-50/80 transition <?php echo $isVoid ? 'opacity-40 line-through' : ''; ?>">
                                <td class="py-4 px-5 font-mono font-bold text-slate-900 text-sm">
                                    <?php echo htmlspecialchars($sale['invoice_no']); ?>
                                </td>
                                <td class="py-4 px-5">
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></div>
                                    <?php if (!empty($sale['customer_phone'])): ?>
                                        <div class="text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-5 font-medium">
                                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase <?php echo $sale['payment_method'] === 'udhaar' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700'; ?>">
                                        <?php echo htmlspecialchars($sale['payment_method']); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 font-mono font-bold text-slate-900 text-sm">
                                    <?php echo $currency . ' ' . number_format((float)$sale['total_amount']); ?>
                                </td>
                                <td class="py-4 px-5 font-medium">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $isVoid ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200'; ?>">
                                        <?php echo strtoupper($sale['status']); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-slate-500 font-medium text-[11px]">
                                    <?php echo date('d-M-Y h:i A', strtotime($sale['created_at'])); ?>
                                </td>
                                <td class="py-4 px-5 text-right font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            type="button" 
                                            onclick="viewReceipt(<?php echo $sale['id']; ?>)" 
                                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition"
                                        >
                                            Receipt
                                        </button>
                                        <?php if (!$isVoid): ?>
                                            <form method="POST" onsubmit="return confirm('Void this sale and restore stock?');" class="inline">
                                                <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                                <button type="submit" name="void_sale" value="1" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold transition">
                                                    Void
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Thermal Receipt Viewer -->
<div id="sales-receipt-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white text-slate-950 max-w-sm w-full rounded-2xl p-6 shadow-2xl flex flex-col max-h-[90vh] border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="font-bold text-sm uppercase tracking-wide">Reprint Receipt</h3>
            <button type="button" onclick="document.getElementById('sales-receipt-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <div id="sales-modal-preview" class="flex-1 overflow-y-auto font-mono text-xs bg-slate-50 p-4 rounded-xl border border-slate-200 mb-4"></div>

        <div class="flex gap-2">
            <button type="button" onclick="window.print()" class="flex-1 py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase tracking-wider hover:bg-slate-800 transition">
                Print Receipt
            </button>
            <button type="button" onclick="document.getElementById('sales-receipt-modal').classList.add('hidden')" class="py-3 px-4 rounded-xl bg-slate-100 text-slate-800 font-bold text-xs uppercase tracking-wider hover:bg-slate-200 transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
async function viewReceipt(saleId) {
    try {
        const res = await fetch(`receipt.php?id=${saleId}&format=json`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('sales-modal-preview').innerHTML = data.html;
            document.getElementById('thermal-receipt').innerHTML = data.html;
            document.getElementById('sales-receipt-modal').classList.remove('hidden');
        } else {
            alert('Could not load receipt: ' + data.error);
        }
    } catch (e) {
        alert('Network error loading receipt');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
