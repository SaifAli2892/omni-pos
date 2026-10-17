<?php
/**
 * OmniPOS - Professional White Full-Screen Terminal
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$currentStoreId = currentStoreId();

// Fetch Categories for current store
$categories = [];
try {
    $cStmt = $pdo->prepare("SELECT * FROM categories WHERE store_id = ? ORDER BY id ASC");
    $cStmt->execute([$currentStoreId]);
    $categories = $cStmt->fetchAll();
} catch (Exception $e) {}

// Fetch Active Products for current store only (Store Isolation)
$products = [];
try {
    $pStmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.store_id = ? AND p.is_active = 1 
        ORDER BY p.id ASC
    ");
    $pStmt->execute([$currentStoreId]);
    $products = $pStmt->fetchAll();
} catch (Exception $e) {}

// Fetch Customers for current store
$customers = [];
try {
    $custStmt = $pdo->prepare("SELECT * FROM customers WHERE store_id = ? ORDER BY id ASC");
    $custStmt->execute([$currentStoreId]);
    $customers = $custStmt->fetchAll();
} catch (Exception $e) {}

$taxRate = getSetting('tax_rate', '0');
?>

<script>
    window.storeTaxRate = <?php echo json_encode($taxRate); ?>;
    window.storeSettings = {
        name: <?php echo json_encode($storeName); ?>,
        address: <?php echo json_encode(getSetting('store_address', 'Lahore, Pakistan')); ?>,
        phone: <?php echo json_encode(getSetting('store_phone', '0300-1234567')); ?>,
        footer: <?php echo json_encode(getSetting('receipt_footer', 'Thank you for your business!')); ?>
    };
</script>

<!-- TILE MODE WIDGET (Renders exclusively inside Scora POS Smart Grid tile) -->
<div id="tile-mode-container" class="hidden h-screen w-full p-4 flex-col justify-between bg-white text-slate-900 select-none box-border">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-black flex items-center justify-center text-xs shadow-sm">
                ⚡
            </div>
            <div>
                <div class="font-extrabold text-xs text-slate-950 tracking-tight leading-tight">OmniPOS Terminal</div>
                <div class="text-[10px] text-emerald-700 font-bold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>System Ready</span>
                </div>
            </div>
        </div>
        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">v1.0</span>
    </div>

    <div class="my-auto py-1">
        <button 
            type="button" 
            onclick="openTerminalFullscreen()" 
            class="w-full py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition"
        >
            <span>Launch POS Terminal</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
        </button>
    </div>

    <div class="flex items-center justify-between text-[10px] text-slate-400 border-t border-slate-100 pt-1.5">
        <span class="truncate"><?php echo htmlspecialchars($storeName); ?></span>
        <button type="button" onclick="openTerminalFullscreen()" class="text-emerald-700 font-bold hover:underline shrink-0">
            Open Fullscreen &rarr;
        </button>
    </div>
</div>

<script>
function openTerminalFullscreen() {
    var url = window.location.protocol + '//' + window.location.host + window.location.pathname + window.location.search;
    window.open(url, '_blank');
}
</script>

<!-- FULL DESKTOP TERMINAL -->
<div id="desktop-terminal-container" class="flex-1 flex flex-col lg:flex-row overflow-hidden w-full h-[calc(100vh-65px)]">

    <!-- LEFT COLUMN: Product Catalog & Search -->
    <div class="flex-1 flex flex-col bg-slate-50 border-r border-slate-200 p-6 overflow-y-auto">
        
        <!-- Search & Barcode Bar -->
        <div class="flex items-center gap-3 mb-5">
            <div class="relative flex-1">
                <input 
                    type="text" 
                    id="product-search-input" 
                    placeholder="Scan barcode or type product name / SKU (Press F2 to focus)..." 
                    autofocus
                    class="w-full pl-4 pr-24 py-3 rounded-xl bg-white border border-slate-300 text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 transition shadow-sm font-medium"
                >
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 text-[11px] font-mono text-slate-700 font-bold border border-slate-300">F2 Scan</span>
                </div>
            </div>

            <!-- Quick Add Customer -->
            <button type="button" onclick="document.getElementById('add-customer-modal').classList.remove('hidden')" class="px-4 py-3 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 text-xs font-bold transition shrink-0 shadow-sm">
                + Customer
            </button>
        </div>

        <!-- Category Text Pills (High Contrast) -->
        <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-4 scrollbar-hide text-xs">
            <button type="button" data-category="0" class="cat-pill px-4 py-2 rounded-lg font-bold bg-emerald-700 text-white border border-emerald-700 transition whitespace-nowrap shadow-sm">
                All Products (<?php echo count($products); ?>)
            </button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" data-category="<?php echo $cat['id']; ?>" class="cat-pill px-4 py-2 rounded-lg font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 transition whitespace-nowrap shadow-sm">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
            <?php endforeach; ?>
            <button type="button" onclick="quickSyncStoreProducts(this)" class="ml-auto px-3.5 py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                <span>🔄 Live Scora Sync</span>
            </button>
        </div>

        <script>
        window.hasScoraToken = <?php echo !empty($cloudToken) ? 'true' : 'false'; ?>;
        window.currentStoreId = <?php echo json_encode($currentStoreId); ?>;

        async function quickSyncStoreProducts(btn) {
            if (!window.hasScoraToken) {
                if (window.showToast) window.showToast('Connecting to Scora for 1-click authorization...', 'info');
                setTimeout(() => {
                    window.location.href = 'oauth.php?connect=1&store_id=' + encodeURIComponent(window.currentStoreId);
                }, 400);
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerText = 'Connecting to Scora API...';
            }
            try {
                const res = await fetch('api/sync.php?action=import_catalog&store_id=' + encodeURIComponent(window.currentStoreId));
                const data = await res.json();
                if (data.success) {
                    window.showToast(data.message || 'Store products synced!', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else if (data.need_auth || (data.error && (data.error.includes('expired') || data.error.includes('refresh token') || data.error.includes('UNAUTHENTICATED')))) {
                    window.showToast('Scora authorization needed. Redirecting...', 'info');
                    setTimeout(() => {
                        window.location.href = data.auth_url || ('oauth.php?connect=1&store_id=' + encodeURIComponent(window.currentStoreId));
                    }, 600);
                } else {
                    window.showToast(data.error || 'Failed to sync', 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerText = '🔄 Live Scora Sync';
                    }
                }
            } catch (e) {
                window.location.href = 'oauth.php?connect=1&store_id=' + encodeURIComponent(window.currentStoreId);
            }
        }
        </script>

        <!-- Products Grid -->
        <div id="products-grid" class="flex-1 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3.5 overflow-y-auto pr-1">
            <?php if (empty($cloudToken)): ?>
                <div class="col-span-full py-16 text-center bg-white rounded-2xl border border-emerald-200 p-8 shadow-sm my-auto max-w-md mx-auto">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl font-black">
                        ⚡
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-1">Connect <?php echo htmlspecialchars($storeName ?: 'Your Store'); ?></h3>
                    <p class="text-xs text-slate-500 mb-6">Authorize OmniPOS via Scora OAuth 2.0 to import your store's live catalog and enable real-time inventory synchronization.</p>
                    <a href="oauth.php?connect=1<?php echo !empty($currentStoreId) ? '&store_id=' . urlencode($currentStoreId) : ''; ?>" class="w-full py-3 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs transition shadow-md flex items-center justify-center gap-2">
                        <span>⚡ 1-Click Authorize with Scora</span>
                    </a>
                </div>
            <?php elseif (empty($products)): ?>
                <div class="col-span-full py-20 text-center bg-white rounded-2xl border border-slate-200 p-8 shadow-sm my-auto">
                    <h3 class="text-base font-bold text-slate-900 mb-1">Product Catalog is Empty</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5">There are no products in your catalog yet. Pull your live store items or add manually.</p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <button type="button" onclick="quickSyncStoreProducts(this)" class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs transition shadow-sm flex items-center gap-1.5">
                            <span>🔄 Live Scora Sync</span>
                        </button>
                        <a href="products.php" class="px-5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition border border-slate-300 shadow-sm">
                            + Add Manually
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <?php foreach ($products as $p): ?>
                    <?php 
                        $stock = (int)$p['stock_qty'];
                        $isOut = ($stock <= 0);
                        $jsonProduct = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div onclick="window.addToCart(<?php echo $jsonProduct; ?>)" class="product-card group relative p-4 rounded-xl cursor-pointer flex flex-col justify-between <?php echo $isOut ? 'opacity-40 pointer-events-none' : ''; ?>">
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-2.5">
                                <span class="text-xs font-mono text-slate-600 font-bold"><?php echo htmlspecialchars($p['barcode'] ?: $p['sku'] ?: ''); ?></span>
                                <span id="stock-badge-<?php echo $p['id']; ?>" class="text-[11px] px-2.5 py-0.5 rounded-full font-bold <?php echo $isOut ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($stock <= 5 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700'); ?>">
                                    <?php echo $isOut ? 'Sold Out' : "{$stock} In Stock"; ?>
                                </span>
                            </div>
                            <h3 class="font-bold text-slate-900 text-sm leading-snug group-hover:text-emerald-700 transition mb-1">
                                <?php echo htmlspecialchars($p['title']); ?>
                            </h3>
                            <p class="text-xs text-slate-500 font-medium"><?php echo htmlspecialchars($p['category_name'] ?: 'General'); ?></p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="font-extrabold text-slate-900 font-sans text-base">
                                <?php echo htmlspecialchars($currency); ?> <?php echo number_format((float)$p['selling_price']); ?>
                            </span>
                            <span class="px-3 py-1 rounded-md bg-emerald-50 text-emerald-700 group-hover:bg-emerald-700 group-hover:text-white font-bold text-xs transition">
                                Add +
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT COLUMN: Current Order & Payment (Clean White Register Panel) -->
    <div class="w-full lg:w-[440px] xl:w-[480px] bg-white flex flex-col justify-between p-6 shrink-0 overflow-y-auto">
        
        <div>
            <!-- Order Header -->
            <div class="flex items-center justify-between gap-2 pb-4 border-b border-slate-200 mb-4">
                <div class="flex items-center gap-2">
                    <span class="font-black text-slate-900 text-sm tracking-tight uppercase">Current Register</span>
                    <span id="cart-count-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 font-mono">0</span>
                </div>
                <button type="button" onclick="window.clearCart()" class="text-xs font-bold text-slate-500 hover:text-rose-600 transition">
                    Clear Cart
                </button>
            </div>

            <!-- Customer Selector -->
            <div class="mb-4">
                <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Customer Ledger (Khata)</label>
                <select id="customer-select" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-900 focus:outline-none focus:border-emerald-600 transition">
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['name']); ?> 
                            <?php if ((float)$c['credit_balance'] > 0): ?>
                                (Udhaar: <?php echo $currency . ' ' . number_format((float)$c['credit_balance']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Cart Items List -->
            <div class="min-h-[160px] max-h-[240px] overflow-y-auto space-y-2 pr-1 mb-4">
                <div id="cart-empty-state" class="py-14 text-center text-slate-400">
                    <p class="text-sm font-semibold text-slate-600">Cart is empty</p>
                    <p class="text-xs text-slate-400 mt-1">Scan a barcode or click any product to begin sale</p>
                </div>
                <div id="cart-items-list" class="space-y-2"></div>
            </div>
        </div>

        <!-- Payment & Calculation Panel -->
        <div class="pt-4 border-t border-slate-200 space-y-3.5">
            
            <!-- Discount & Payment Method -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Discount (F8)</label>
                    <input 
                        type="number" 
                        id="discount-input" 
                        placeholder="0.00" 
                        oninput="renderCart()" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600"
                    >
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Payment Method</label>
                    <select id="payment-method-select" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-600">
                        <option value="cash">Cash (Naqad)</option>
                        <option value="card">Card (Debit/Credit)</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">EasyPaisa</option>
                        <option value="udhaar">Khata (Udhaar/Credit)</option>
                    </select>
                </div>
            </div>

            <!-- Cash Tendered -->
            <div>
                <div class="flex items-center justify-between text-[11px] text-slate-600 font-bold mb-1">
                    <span>Cash Tendered</span>
                    <div class="flex gap-1 font-mono text-xs">
                        <button type="button" onclick="setQuickTender('exact')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold">Exact</button>
                        <button type="button" onclick="setQuickTender(500)" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-800">500</button>
                        <button type="button" onclick="setQuickTender(1000)" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-800">1000</button>
                        <button type="button" onclick="setQuickTender(5000)" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-800">5000</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input 
                        type="number" 
                        id="paid-amount-input" 
                        placeholder="Amount Received" 
                        oninput="this.dataset.touched = 'true'; renderCart();" 
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold text-sm focus:outline-none focus:border-emerald-600"
                    >
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-600">Change Due:</span>
                        <span id="summary-change-due" class="font-mono font-bold text-emerald-800 text-sm sm:text-base"><?php echo htmlspecialchars($currency); ?> 0</span>
                    </div>
                </div>
            </div>

            <!-- Total Box (Clean High Contrast White/Emerald Theme) -->
            <div class="p-4 rounded-xl bg-emerald-50/80 border border-emerald-200 flex items-center justify-between">
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-emerald-950 font-bold">Total Payable</div>
                    <div class="text-xs text-slate-600 font-medium">Subtotal: <span id="summary-subtotal" class="font-bold text-slate-900"><?php echo htmlspecialchars($currency); ?> 0</span></div>
                </div>
                <div class="text-right">
                    <div id="summary-grand-total" class="text-2xl font-black font-sans text-emerald-800">
                        <?php echo htmlspecialchars($currency); ?> 0
                    </div>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button 
                type="button" 
                id="btn-checkout" 
                onclick="window.processCheckout()" 
                class="w-full py-4 px-6 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm uppercase tracking-wide transition shadow-sm active:scale-[0.99] flex items-center justify-center gap-2"
            >
                Complete Sale & Print [F9]
            </button>

        </div>

    </div>

</div>

<!-- Modal: Thermal Receipt Preview -->
<div id="receipt-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white text-slate-900 max-w-sm w-full rounded-2xl p-6 shadow-2xl flex flex-col max-h-[90vh] border border-slate-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="font-bold text-sm uppercase tracking-wide">Receipt Preview</h3>
            <button type="button" onclick="window.closeReceiptModal()" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <div id="modal-receipt-preview" class="flex-1 overflow-y-auto font-mono text-xs bg-slate-50 p-4 rounded-xl border border-slate-200 mb-4"></div>

        <div class="flex gap-2">
            <button type="button" onclick="window.printCurrentReceipt()" class="flex-1 py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase tracking-wider hover:bg-slate-800 transition">
                Print Receipt
            </button>
            <button type="button" onclick="window.closeReceiptModal()" class="py-3 px-4 rounded-xl bg-slate-100 text-slate-800 font-bold text-xs uppercase tracking-wider hover:bg-slate-200 transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Customer -->
<div id="add-customer-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-md w-full rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="font-bold text-sm text-slate-900">Add Customer</h3>
            <button type="button" onclick="document.getElementById('add-customer-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 font-bold">&times;</button>
        </div>

        <form id="quick-customer-form" onsubmit="handleQuickCustomerSubmit(event)" class="space-y-3.5">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Customer Name *</label>
                <input type="text" id="new-cust-name" required placeholder="Full Name" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number *</label>
                <input type="text" id="new-cust-phone" required placeholder="0300-1234567" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Address / Shop Location</label>
                <input type="text" id="new-cust-address" placeholder="Location details" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>
            <div class="pt-2 flex gap-2">
                <button type="submit" class="flex-1 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs transition">
                    Save Customer
                </button>
                <button type="button" onclick="document.getElementById('add-customer-modal').classList.add('hidden')" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/pos.js"></script>
<script>
async function handleQuickCustomerSubmit(e) {
    e.preventDefault();
    const name = document.getElementById('new-cust-name').value.trim();
    const phone = document.getElementById('new-cust-phone').value.trim();
    const address = document.getElementById('new-cust-address').value.trim();

    try {
        const res = await fetch('customers.php?action=quick_create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, phone, address })
        });
        const data = await res.json();
        if (data.success) {
            window.showToast('Customer added successfully', 'success');
            const select = document.getElementById('customer-select');
            const opt = document.createElement('option');
            opt.value = data.customer_id;
            opt.innerText = name;
            opt.selected = true;
            select.appendChild(opt);
            document.getElementById('add-customer-modal').classList.add('hidden');
            document.getElementById('quick-customer-form').reset();
        } else {
            window.showToast(data.error || 'Failed to add customer', 'error');
        }
    } catch (err) {
        window.showToast('Network error saving customer', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
