<?php
/**
 * OmniPOS - System Settings & Automated Store Hub
 * Professional Clean White Theme (Zero-Config Store Connection)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/sync.php';
require_once __DIR__ . '/includes/header.php';

$message = '';
$messageType = 'success';

// Save Outlet Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = [
        'store_name', 'store_phone', 'store_address', 'receipt_footer',
        'currency_symbol', 'tax_rate'
    ];

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            setSetting($k, trim($_POST[$k]));
        }
    }

    $message = 'Outlet details updated successfully!';
    $messageType = 'success';
}

// Fetch current values
$storeName   = getSetting('store_name', 'Retail Store');
$storePhone  = getSetting('store_phone', '');
$storeAddress= getSetting('store_address', '');
$receiptFoot = getSetting('receipt_footer', 'Thank you for shopping with us!');
$currency    = getSetting('currency_symbol', 'Rs.');
$taxRate     = getSetting('tax_rate', '0');

$currentStoreId = currentStoreId();
$cloudDomain = getSetting('cloud_store_domain', 'scora.com.pk');
$cloudToken  = getStoreAccessToken($currentStoreId);
$lastSync    = getSetting('cloud_last_sync', '');
$isConnected = !empty($cloudToken);

// Fetch recent sync logs strictly for this store
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sync_logs WHERE store_id = ? ORDER BY id DESC LIMIT 15");
    $stmt->execute([$currentStoreId]);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Store & System Settings</h1>
            <p class="text-xs text-slate-500 mt-1">Configure physical outlet details, receipt template, and online store pairing.</p>
        </div>

        <div class="flex items-center gap-2">
            <?php if ($isConnected): ?>
                <button type="button" onclick="triggerAutoSync()" id="btn-auto-sync" class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-sm">
                    Re-Sync Store Catalog
                </button>
                <button type="button" onclick="openConnectModal()" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-bold transition shadow-sm">
                    Switch Store
                </button>
            <?php else: ?>
                <button type="button" onclick="openConnectModal()" class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-sm">
                    Connect Online Store
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl text-xs font-semibold flex items-center gap-2 <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        <!-- Card 1: Cloud Store Connection Status -->
        <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Online Store Connection</h2>
                <?php if ($isConnected): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Connected ✓
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono bg-slate-100 text-slate-700 border border-slate-300">
                        Standalone (Not Connected)
                    </span>
                <?php endif; ?>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Connected Store Domain:</span>
                    <span class="font-bold font-mono text-slate-900"><?php echo $isConnected ? htmlspecialchars($cloudDomain) : 'None (Standalone POS)'; ?></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Online Stock Sync:</span>
                    <span class="font-bold <?php echo $isConnected ? 'text-emerald-700' : 'text-slate-600'; ?>">
                        <?php echo $isConnected ? 'Active (Real-Time)' : 'Disabled (Local Counter Only)'; ?>
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Last Synchronized:</span>
                    <span class="font-mono text-slate-600 font-medium"><?php echo $isConnected && !empty($lastSync) ? htmlspecialchars($lastSync) : 'Never'; ?></span>
                </div>
            </div>

            <p class="text-xs text-slate-500 leading-relaxed">
                <?php if ($isConnected): ?>
                    Your POS is synchronized with <strong class="text-slate-700"><?php echo htmlspecialchars($cloudDomain); ?></strong>. Any sales made on this counter adjust inventory levels in real-time.
                <?php else: ?>
                    This POS is currently running in <strong>Standalone Local Mode</strong>. All sales, products, and Khata are stored locally on this machine. When installed on a live store (e.g. via Scora), it pairs automatically without manual token setup.
                <?php endif; ?>
            </p>

            <div class="pt-2">
                <?php if ($isConnected): ?>
                    <button type="button" onclick="triggerAutoSync()" class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition">
                        Sync Store Catalog Now
                    </button>
                <?php else: ?>
                    <button type="button" onclick="openConnectModal()" class="w-full py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs transition">
                        Connect Online Store
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 2: Outlet & Receipt Info -->
        <form method="POST" class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Outlet & Thermal Receipt Info</h2>
                <span class="text-[11px] font-mono text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">Thermal 80mm/58mm</span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Store / Outlet Display Name</label>
                <input 
                    type="text" 
                    name="store_name" 
                    value="<?php echo htmlspecialchars($storeName); ?>" 
                    placeholder="e.g. My Retail Outlet"
                    required 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs font-semibold focus:outline-none focus:border-emerald-600"
                >
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Phone</label>
                    <input 
                        type="text" 
                        name="store_phone" 
                        value="<?php echo htmlspecialchars($storePhone); ?>" 
                        placeholder="e.g. 0300-1234567"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Currency Symbol</label>
                    <input 
                        type="text" 
                        name="currency_symbol" 
                        value="<?php echo htmlspecialchars($currency); ?>" 
                        placeholder="Rs."
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs font-mono focus:outline-none focus:border-emerald-600"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Store Address</label>
                <input 
                    type="text" 
                    name="store_address" 
                    value="<?php echo htmlspecialchars($storeAddress); ?>" 
                    placeholder="e.g. Shop #12, Commercial Market, Lahore"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600"
                >
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Receipt Footer Note</label>
                <input 
                    type="text" 
                    name="receipt_footer" 
                    value="<?php echo htmlspecialchars($receiptFoot); ?>" 
                    placeholder="Thank you for shopping with us! No return without receipt."
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600"
                >
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" name="save_settings" value="1" class="py-2.5 px-6 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider transition shadow-sm">
                    Save Changes
                </button>
            </div>
        </form>

    </div>


    <!-- Sync Activity Logs -->
    <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Store Synchronization Activity</h2>
            <span class="text-xs text-slate-400 font-medium">Recent Activity</span>
        </div>

        <?php if (empty($logs)): ?>
            <p class="py-8 text-center text-slate-400 text-xs font-medium">No sync events recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-100 text-[11px] uppercase font-bold">
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Event</th>
                            <th class="py-2.5 px-3">Details</th>
                            <th class="py-2.5 px-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $log['status'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'; ?>">
                                        <?php echo strtoupper($log['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-bold text-slate-900"><?php echo htmlspecialchars($log['event']); ?></td>
                                <td class="py-3 px-3 text-slate-600 text-xs"><?php echo htmlspecialchars($log['details']); ?></td>
                                <td class="py-3 px-3 text-right text-slate-400 text-[11px] font-mono"><?php echo date('d-M h:i A', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Developer Testing Tools (Wipe / Reset Store Data) -->
    <div class="mt-6 p-6 rounded-xl bg-white border border-rose-100 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-rose-100 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <h2 class="font-bold text-sm text-slate-900 uppercase tracking-wider">Testing & Multi-Store Reset Tools</h2>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">Dev Testing</span>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed mb-4">
            When switching between Store A and Store B during manual token testing, you can wipe the local catalog, customers, and tokens for <strong><?php echo htmlspecialchars($storeName); ?> (Store ID: <?php echo htmlspecialchars($currentStoreId); ?>)</strong> with 1-click so test data does not mix.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" onclick="executeWipeStore()" id="btn-wipe-store" class="px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 font-bold text-xs transition flex items-center gap-2">
                <span>🗑️ Wipe Current Store Data & Token</span>
            </button>
            <span class="text-[11px] text-slate-400">Only affects active store (ID: <?php echo htmlspecialchars($currentStoreId); ?>). Other stores remain intact.</span>
        </div>
    </div>

</div>

<!-- Modal: Switch / Connect Store -->
<div id="connect-store-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-lg w-full rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Connect Store with Scora</h3>
                <p class="text-[11px] text-slate-500">Active Store Context: <strong class="text-slate-800"><?php echo htmlspecialchars($storeName); ?> (Store ID: <?php echo htmlspecialchars($currentStoreId); ?>)</strong></p>
            </div>
            <button type="button" onclick="document.getElementById('connect-store-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <div class="space-y-4">
            <!-- 1-Click Official OAuth -->
            <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-950 uppercase tracking-wide">1-Click Scora OAuth 2.0</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-200 text-emerald-900">Official</span>
                </div>
                <p class="text-xs text-emerald-800 leading-relaxed">
                    Connects directly to your Scora Store Admin, automatically generates this store's private OAuth credentials, and pulls your real catalog in real-time.
                </p>
                <div class="pt-2">
                    <a href="oauth.php?connect=1<?php echo !empty($currentStoreId) ? '&store_id=' . urlencode($currentStoreId) : ''; ?>" class="w-full py-3 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition shadow-sm">
                        <span>⚡ 1-Click Authorize Store via Scora</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const activeStoreId = <?php echo json_encode($currentStoreId); ?>;

function openConnectModal() {
    document.getElementById('connect-store-modal').classList.remove('hidden');
}

async function triggerAutoSync() {
    const btn = document.getElementById('btn-auto-sync');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Syncing...';
    }

    try {
        const res = await fetch('api/sync.php?action=import_catalog&store_id=' + encodeURIComponent(activeStoreId));
        const data = await res.json();
        if (data.success) {
            window.showToast(data.message || 'Store catalog synced successfully!', 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else if (data.need_auth) {
            window.showToast('Authentication required. Redirecting to 1-click connect...', 'info');
            setTimeout(() => {
                window.location.href = data.auth_url || ('oauth.php?connect=1&store_id=' + encodeURIComponent(activeStoreId));
            }, 600);
        } else {
            window.showToast(data.error || 'Sync error', 'error');
        }
    } catch (e) {
        window.showToast('Network error during auto-sync', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerText = 'Re-Sync Store Catalog';
        }
    }
}

async function executeWipeStore() {
    if (!confirm('Are you sure you want to completely wipe all products, orders, and tokens for ' + activeStoreId + '? This will reset this store to a clean slate.')) {
        return;
    }
    const btn = document.getElementById('btn-wipe-store');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Wiping...';
    }
    try {
        const res = await fetch('api/sync.php?action=wipe_store&store_id=' + encodeURIComponent(activeStoreId));
        const data = await res.json();
        if (data.success) {
            window.showToast(data.message || 'Store data wiped successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.showToast(data.error || 'Failed to wipe store data', 'error');
        }
    } catch (e) {
        window.showToast('Network error', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerText = '🗑️ Wipe Current Store Data & Token';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
