<?php
/**
 * OmniPOS - Categories Management
 * Professional Clean White Theme (Full Screen)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$message = '';
$messageType = 'success';
$currentStoreId = currentStoreId();

// Handle Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $id   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));

    if (empty($name)) {
        $message = 'Category name is required!';
        $messageType = 'error';
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ? AND store_id = ?");
                $stmt->execute([$name, $slug, $id, $currentStoreId]);
                $message = 'Category updated successfully!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (store_id, name, slug) VALUES (?, ?, ?)");
                $stmt->execute([$currentStoreId, $name, $slug]);
                $message = 'Category created successfully!';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND store_id = ?");
        $stmt->execute([$delId, $currentStoreId]);
        $message = 'Category deleted successfully!';
    } catch (Exception $e) {
        $message = 'Cannot delete category in use by products.';
        $messageType = 'error';
    }
}

// Fetch Categories with product counts
$categories = [];
try {
    $sql = "
        SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.store_id = ? AND p.is_active = 1 
        WHERE c.store_id = ?
        GROUP BY c.id 
        ORDER BY c.name ASC
    ";
    $cStmt = $pdo->prepare($sql);
    $cStmt->execute([$currentStoreId, $currentStoreId]);
    $categories = $cStmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Product Categories</h1>
            <p class="text-xs text-slate-500 mt-1">Organize your store items into logical departments and POS quick-tabs.</p>
        </div>

        <button type="button" onclick="openCategoryModal()" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm">
            + New Category
        </button>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl text-xs font-semibold flex items-center gap-2 <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Full-Width Categories Table -->
    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold">
                        <th class="py-3.5 px-5">Category Name</th>
                        <th class="py-3.5 px-5">Slug</th>
                        <th class="py-3.5 px-5">Products Count</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="py-16 text-center text-slate-400 font-medium">
                                No categories found. Click "+ New Category" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($cat['name']); ?></div>
                                </td>
                                <td class="py-4 px-5 font-mono text-slate-500 font-medium">
                                    <?php echo htmlspecialchars($cat['slug']); ?>
                                </td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold font-mono bg-slate-100 text-slate-700">
                                        <?php echo $cat['product_count']; ?> Products
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="products.php?cat=<?php echo $cat['id']; ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition">
                                            View Products
                                        </a>
                                        <button type="button" onclick='editCategory(<?php echo json_encode($cat); ?>)' class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition">
                                            Edit
                                        </button>
                                        <a href="categories.php?delete=<?php echo $cat['id']; ?>" onclick="return confirm('Delete this category?');" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold transition">
                                            Delete
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

<!-- Modal: Add / Edit Category -->
<div id="category-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-md w-full rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 id="category-modal-title" class="font-bold text-sm text-slate-900 uppercase tracking-wider">Add Category</h3>
            <button type="button" onclick="closeCategoryModal()" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="category_id" id="modal-category-id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Category Name *</label>
                <input type="text" name="name" id="modal-category-name" required placeholder="e.g. Beverages & Dairy" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600 font-medium">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Slug / Code</label>
                <input type="text" name="slug" id="modal-category-slug" placeholder="e.g. beverages-dairy (optional)" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <button type="button" onclick="closeCategoryModal()" class="py-2.5 px-5 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" name="save_category" value="1" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                    Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('category-modal-title').innerText = 'Add New Category';
    document.getElementById('modal-category-id').value = '';
    document.getElementById('modal-category-name').value = '';
    document.getElementById('modal-category-slug').value = '';
    document.getElementById('category-modal').classList.remove('hidden');
}

function editCategory(cat) {
    document.getElementById('category-modal-title').innerText = 'Edit Category';
    document.getElementById('modal-category-id').value = cat.id;
    document.getElementById('modal-category-name').value = cat.name;
    document.getElementById('modal-category-slug').value = cat.slug;
    document.getElementById('category-modal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('category-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
