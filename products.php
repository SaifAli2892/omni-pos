<?php
/**
 * OmniPOS - Inventory & Product Catalog Management
 * Professional Clean White Theme (Full Screen)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$message = '';
$messageType = 'success';
$currentStoreId = currentStoreId();

// Handle Add / Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $id         = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $title      = trim($_POST['title'] ?? '');
    $barcode    = trim($_POST['barcode'] ?? '') ?: null;
    $sku        = trim($_POST['sku'] ?? '') ?: null;
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $costPrice  = (float)($_POST['cost_price'] ?? 0);
    $sellPrice  = (float)($_POST['selling_price'] ?? 0);
    $stockQty   = (int)($_POST['stock_qty'] ?? 0);
    $alertQty   = (int)($_POST['alert_qty'] ?? 5);
    $scoraId    = trim($_POST['scora_product_id'] ?? '') ?: null;

    if (empty($title) || $sellPrice <= 0) {
        $message = 'Product title and valid selling price are required!';
        $messageType = 'error';
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        title = ?, barcode = ?, sku = ?, category_id = ?, 
                        cost_price = ?, selling_price = ?, stock_qty = ?, alert_qty = ?, 
                        scora_product_id = ?
                    WHERE id = ? AND store_id = ?
                ");
                $stmt->execute([$title, $barcode, $sku, $categoryId, $costPrice, $sellPrice, $stockQty, $alertQty, $scoraId, $id, $currentStoreId]);
                $message = 'Product updated successfully!';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products (store_id, title, barcode, sku, category_id, cost_price, selling_price, stock_qty, alert_qty, scora_product_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$currentStoreId, $title, $barcode, $sku, $categoryId, $costPrice, $sellPrice, $stockQty, $alertQty, $scoraId]);
                $message = 'Product added successfully!';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle Quick Delete / Deactivate
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND store_id = ?");
        $stmt->execute([$delId, $currentStoreId]);
        $message = 'Product removed from active catalog.';
    } catch (Exception $e) {
        $message = 'Delete error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch Categories for dropdown
$categories = [];
try {
    $cStmt = $pdo->prepare("SELECT * FROM categories WHERE store_id = ? ORDER BY name ASC");
    $cStmt->execute([$currentStoreId]);
    $categories = $cStmt->fetchAll();
} catch (Exception $e) {}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$filterCat = (int)($_GET['cat'] ?? 0);

$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.store_id = ? AND p.is_active = 1";
$params = [$currentStoreId];

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($filterCat > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $filterCat;
}

$query .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productList = $stmt->fetchAll();
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Top Header Full Screen -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Product Catalog & Inventory</h1>
            <p class="text-xs text-slate-500 mt-1">Manage physical retail stock, pricing, barcodes, and SKU codes.</p>
        </div>

        <button type="button" onclick="openProductModal()" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm">
            + New Product
        </button>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl text-xs font-semibold flex items-center gap-2 <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Controls -->
    <form method="GET" class="p-4 rounded-xl bg-white border border-slate-200 flex flex-wrap items-center justify-between gap-3 mb-6 shadow-sm">
        <div class="flex-1 min-w-[240px]">
            <input 
                type="text" 
                name="q" 
                value="<?php echo htmlspecialchars($search); ?>" 
                placeholder="Search products by title, barcode, or SKU..." 
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600 font-medium"
            >
        </div>

        <div class="w-52">
            <select name="cat" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-700 text-xs focus:outline-none font-medium">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $filterCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition">
            Filter
        </button>
    </form>

    <!-- Full Screen Products Table -->
    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold">
                        <th class="py-3.5 px-5">Product Title</th>
                        <th class="py-3.5 px-5">Barcode / SKU</th>
                        <th class="py-3.5 px-5">Category</th>
                        <th class="py-3.5 px-5">Cost Price</th>
                        <th class="py-3.5 px-5">Selling Price</th>
                        <th class="py-3.5 px-5">Available Stock</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($productList)): ?>
                        <tr>
                            <td colspan="7" class="py-16 text-center text-slate-400 font-medium">
                                No products in inventory yet. Click <strong>"+ Add New Product"</strong> above to add your retail items.
                            </td>

                        </tr>
                    <?php else: ?>
                        <?php foreach ($productList as $p): ?>
                            <?php 
                                $stock = (int)$p['stock_qty'];
                                $alert = (int)$p['alert_qty'];
                                $isLow = ($stock <= $alert && $stock > 0);
                                $isOut = ($stock <= 0);
                                $profit = (float)$p['selling_price'] - (float)$p['cost_price'];
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($p['title']); ?></div>
                                    <div class="text-[11px] text-emerald-700 font-mono mt-0.5 font-medium">Margin: +<?php echo $currency . ' ' . number_format($profit); ?></div>
                                </td>
                                <td class="py-4 px-5 font-mono text-slate-600 font-medium">
                                    <?php echo htmlspecialchars($p['barcode'] ?: ($p['sku'] ?: '—')); ?>
                                </td>
                                <td class="py-4 px-5 text-slate-600 font-medium">
                                    <?php echo htmlspecialchars($p['category_name'] ?: 'General'); ?>
                                </td>
                                <td class="py-4 px-5 font-mono text-slate-500 font-medium">
                                    <?php echo $currency . ' ' . number_format((float)$p['cost_price']); ?>
                                </td>
                                <td class="py-4 px-5 font-mono font-bold text-slate-900 text-sm">
                                    <?php echo $currency . ' ' . number_format((float)$p['selling_price']); ?>
                                </td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold font-mono <?php echo $isOut ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($isLow ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700'); ?>">
                                        <?php echo $isOut ? 'Out of Stock' : ($isLow ? "Low: {$stock}" : "{$stock} In Stock"); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            type="button" 
                                            onclick='editProduct(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition"
                                        >
                                            Edit
                                        </button>
                                        <a 
                                            href="products.php?delete=<?php echo $p['id']; ?>" 
                                            onclick="return confirm('Remove this product?');" 
                                            class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold transition"
                                        >
                                            Remove
                                        </a>
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

<!-- Modal: Add / Edit Product -->
<div id="product-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-xl w-full rounded-2xl p-6 shadow-2xl overflow-y-auto max-h-[92vh]">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 id="product-modal-title" class="font-bold text-sm text-slate-900 uppercase tracking-wider">Add New Product</h3>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="product_id" id="modal-product-id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Product Title *</label>
                <input type="text" name="title" id="modal-title" required placeholder="e.g. Tapal Danedar Tea 450g" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Barcode</label>
                    <input type="text" name="barcode" id="modal-barcode" placeholder="Scan or enter barcode" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">SKU Code</label>
                    <input type="text" name="sku" id="modal-sku" placeholder="SKU-001" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                    <select name="category_id" id="modal-category" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-800 text-xs focus:outline-none focus:border-emerald-600 font-medium">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Online Store Product ID (Optional)</label>
                    <input type="text" name="scora_product_id" id="modal-scora-id" placeholder="Product ID mapping" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cost Price (Kharid)</label>
                    <input type="number" step="0.01" name="cost_price" id="modal-cost-price" placeholder="0.00" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600 font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Selling Price (Farokht) *</label>
                    <input type="number" step="0.01" name="selling_price" id="modal-selling-price" required placeholder="0.00" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold text-xs focus:outline-none focus:border-emerald-600">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Stock Quantity</label>
                    <input type="number" name="stock_qty" id="modal-stock-qty" value="10" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600 font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Low Stock Warning Threshold</label>
                    <input type="number" name="alert_qty" id="modal-alert-qty" value="5" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600 font-semibold">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <button type="button" onclick="closeProductModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" name="save_product" value="1" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                    Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('product-modal-title').innerText = 'Add New Product';
    document.getElementById('modal-product-id').value = '';
    document.getElementById('modal-title').value = '';
    document.getElementById('modal-barcode').value = '';
    document.getElementById('modal-sku').value = '';
    document.getElementById('modal-category').value = '';
    document.getElementById('modal-cost-price').value = '';
    document.getElementById('modal-selling-price').value = '';
    document.getElementById('modal-stock-qty').value = '10';
    document.getElementById('modal-alert-qty').value = '5';
    document.getElementById('modal-scora-id').value = '';
    document.getElementById('product-modal').classList.remove('hidden');
}

function editProduct(p) {
    document.getElementById('product-modal-title').innerText = 'Edit Product';
    document.getElementById('modal-product-id').value = p.id;
    document.getElementById('modal-title').value = p.title;
    document.getElementById('modal-barcode').value = p.barcode || '';
    document.getElementById('modal-sku').value = p.sku || '';
    document.getElementById('modal-category').value = p.category_id || '';
    document.getElementById('modal-cost-price').value = p.cost_price;
    document.getElementById('modal-selling-price').value = p.selling_price;
    document.getElementById('modal-stock-qty').value = p.stock_qty;
    document.getElementById('modal-alert-qty').value = p.alert_qty;
    document.getElementById('modal-scora-id').value = p.scora_product_id || '';
    document.getElementById('product-modal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('product-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
