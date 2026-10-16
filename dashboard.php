<?php
/**
 * OmniPOS - Analytics & Reports Dashboard
 * Professional Clean White Theme (Full Screen)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$currency = getSetting('currency_symbol', 'Rs.');

// 1. Sales & Revenue Metrics
$todayRevenue = 0;
$todayOrders = 0;
$monthRevenue = 0;
$totalProfit = 0;

try {
    // Today
    $tStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as rev, COUNT(id) as orders FROM sales WHERE status = 'completed' AND DATE(created_at) = CURDATE()");
    $tRow = $tStmt->fetch();
    $todayRevenue = (float)($tRow['rev'] ?? 0);
    $todayOrders  = (int)($tRow['orders'] ?? 0);

    // This Month
    $mStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as rev FROM sales WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $mRow = $mStmt->fetch();
    $monthRevenue = (float)($mRow['rev'] ?? 0);

    // Gross Profit Calculation
    $pStmt = $pdo->query("
        SELECT COALESCE(SUM((si.unit_price - si.cost_price) * si.quantity), 0) as profit 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.status = 'completed'
    ");
    $pRow = $pStmt->fetch();
    $totalProfit = (float)($pRow['profit'] ?? 0);

} catch (Exception $e) {}

// 2. Payment Method Breakdown
$paymentBreakdown = [];
try {
    $pbStmt = $pdo->query("
        SELECT payment_method, COUNT(id) as count, COALESCE(SUM(total_amount), 0) as total 
        FROM sales 
        WHERE status = 'completed' 
        GROUP BY payment_method
    ");
    $paymentBreakdown = $pbStmt->fetchAll();
} catch (Exception $e) {}

// 3. Top Selling Products
$topProducts = [];
try {
    $tpStmt = $pdo->query("
        SELECT product_title, SUM(quantity) as total_qty, SUM(subtotal) as total_sales 
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.status = 'completed'
        GROUP BY product_title 
        ORDER BY total_qty DESC 
        LIMIT 5
    ");
    $topProducts = $tpStmt->fetchAll();
} catch (Exception $e) {}

// 4. Low Stock Alerts
$lowStockItems = [];
try {
    $lsStmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND p.stock_qty <= p.alert_qty 
        ORDER BY p.stock_qty ASC 
        LIMIT 8
    ");
    $lowStockItems = $lsStmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Business Reports & Performance</h1>
            <p class="text-xs text-slate-500 mt-1">Real-time revenue, gross profitability, payment channels, and inventory health.</p>
        </div>

        <a href="index.php" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm">
            Open Terminal
        </a>
    </div>

    <!-- 4 Clean White KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Today's Revenue</span>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono">
                <?php echo $currency . ' ' . number_format($todayRevenue); ?>
            </div>
            <p class="text-xs text-slate-500 mt-1.5 font-medium"><?php echo $todayOrders; ?> bills generated today</p>
        </div>

        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Current Month Sales</span>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono">
                <?php echo $currency . ' ' . number_format($monthRevenue); ?>
            </div>
            <p class="text-xs text-slate-500 mt-1.5 font-medium"><?php echo date('F Y'); ?> to date</p>
        </div>

        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Total Gross Profit (Munafa)</span>
            <div class="text-2xl sm:text-3xl font-black text-emerald-700 font-mono">
                <?php echo $currency . ' ' . number_format($totalProfit); ?>
            </div>
            <p class="text-xs text-slate-500 mt-1.5 font-medium">Selling Price minus Cost Price</p>
        </div>

        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Low Stock Alerts</span>
            <div class="text-2xl sm:text-3xl font-black text-amber-700 font-mono">
                <?php echo count($lowStockItems); ?> Items
            </div>
            <p class="text-xs text-slate-500 mt-1.5 font-medium">Below warning threshold</p>
        </div>
    </div>

    <!-- 2 Column Spacious Data Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        <!-- Payment Method Breakdown -->
        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Payment Channels</h2>
                <span class="text-xs text-slate-400 font-medium">Breakdown</span>
            </div>

            <?php if (empty($paymentBreakdown)): ?>
                <p class="text-xs text-slate-400 py-10 text-center font-medium">No sales transactions recorded yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($paymentBreakdown as $pb): ?>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-900 text-xs uppercase"><?php echo htmlspecialchars($pb['payment_method']); ?></div>
                                <div class="text-[11px] text-slate-500 mt-0.5"><?php echo $pb['count']; ?> transactions</div>
                            </div>
                            <div class="text-right font-mono font-bold text-slate-900 text-sm">
                                <?php echo $currency . ' ' . number_format((float)$pb['total']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Selling Products -->
        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Top Selling Items</h2>
                <span class="text-xs text-slate-400 font-medium">Fast Moving</span>
            </div>

            <?php if (empty($topProducts)): ?>
                <p class="text-xs text-slate-400 py-10 text-center font-medium">Make sales from the register to view top products.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($topProducts as $idx => $tp): ?>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-md bg-slate-200 text-slate-700 font-bold font-mono text-xs flex items-center justify-center">
                                    <?php echo $idx + 1; ?>
                                </span>
                                <div class="font-bold text-slate-900 text-xs truncate max-w-[220px]">
                                    <?php echo htmlspecialchars($tp['product_title']); ?>
                                </div>
                            </div>
                            <div class="text-right font-mono text-xs">
                                <div class="font-bold text-slate-900"><?php echo $tp['total_qty']; ?> sold</div>
                                <div class="text-[11px] text-slate-500"><?php echo $currency . ' ' . number_format((float)$tp['total_sales']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Low Stock Inventory Alerts -->
    <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Low Stock & Reorder Warnings</h2>
            <a href="products.php" class="text-xs text-emerald-700 hover:underline font-bold">Manage All Stock &rarr;</a>
        </div>

        <?php if (empty($lowStockItems)): ?>
            <p class="text-xs text-slate-400 py-8 text-center font-medium">All products are adequately stocked.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-[11px] uppercase font-bold">
                            <th class="py-3 px-4">Product Name</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Selling Price</th>
                            <th class="py-3 px-4">Current Stock</th>
                            <th class="py-3 px-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($lowStockItems as $item): ?>
                            <?php $isZero = ((int)$item['stock_qty'] <= 0); ?>
                            <tr>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <?php echo htmlspecialchars($item['category_name'] ?: 'General'); ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-900">
                                    <?php echo $currency . ' ' . number_format((float)$item['selling_price']); ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold <?php echo $isZero ? 'text-rose-700' : 'text-amber-700'; ?>">
                                    <?php echo $item['stock_qty']; ?> Left (Threshold: <?php echo $item['alert_qty']; ?>)
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $isZero ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200'; ?>">
                                        <?php echo $isZero ? 'Sold Out' : 'Reorder'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
