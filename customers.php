<?php
/**
 * OmniPOS - Khata (Udhaar) & Customer Ledger Management
 * Professional Clean White Theme (Full Screen)
 */

require_once __DIR__ . '/config/database.php';

// Handle Quick Create via AJAX from POS screen
if (isset($_GET['action']) && $_GET['action'] === 'quick_create') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = trim($input['address'] ?? '');

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Name and phone are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address, credit_balance) VALUES (?, ?, ?, 0.00)");
        $stmt->execute([$name, $phone, $address]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'customer_id' => $newId, 'name' => $name]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Phone number already exists.']);
    }
    exit;
}

require_once __DIR__ . '/includes/header.php';

$message = '';
$messageType = 'success';

// Handle Record Vasooli (Payment Received for Khata)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_payment'])) {
    $custId = (int)$_POST['customer_id'];
    $amount = (float)$_POST['amount'];
    $notes  = trim($_POST['notes'] ?? 'Khata payment received');

    if ($amount <= 0) {
        $message = 'Please enter a valid payment amount.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $cStmt = $pdo->prepare("SELECT credit_balance FROM customers WHERE id = ?");
            $cStmt->execute([$custId]);
            $cust = $cStmt->fetch();

            if ($cust) {
                $oldBal = (float)$cust['credit_balance'];
                $newBal = max(0, $oldBal - $amount);

                $upStmt = $pdo->prepare("UPDATE customers SET credit_balance = ? WHERE id = ?");
                $upStmt->execute([$newBal, $custId]);

                $lStmt = $pdo->prepare("
                    INSERT INTO customer_ledgers (customer_id, sale_id, type, amount, balance_after, notes)
                    VALUES (?, NULL, 'payment_received', ?, ?, ?)
                ");
                $lStmt->execute([$custId, $amount, $newBal, $notes]);

                $pdo->commit();
                $message = "Vasooli of {$currency} " . number_format($amount) . " recorded. New balance: {$currency} " . number_format($newBal);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Payment error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle Add / Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $id      = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $initialBalance = (float)($_POST['initial_balance'] ?? 0);

    if (empty($name)) {
        $message = 'Customer name is required.';
        $messageType = 'error';
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $address, $id]);
                $message = 'Customer details updated successfully!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, credit_balance) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $address, $initialBalance]);
                $newCustId = $pdo->lastInsertId();

                if ($initialBalance > 0) {
                    $lStmt = $pdo->prepare("INSERT INTO customer_ledgers (customer_id, type, amount, balance_after, notes) VALUES (?, 'credit_sale', ?, ?, 'Opening Balance')");
                    $lStmt->execute([$newCustId, $initialBalance, $initialBalance]);
                }
                $message = 'Customer registered successfully!';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch Customers List & Metrics
$search = trim($_GET['q'] ?? '');
$filterUdhaarOnly = isset($_GET['udhaar_only']);

$where = ["1=1"];
$params = [];
if (!empty($search)) {
    $where[] = "(name LIKE ? OR phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($filterUdhaarOnly) {
    $where[] = "credit_balance > 0";
}

$whereClause = implode(" AND ", $where);
$cQuery = "SELECT * FROM customers WHERE {$whereClause} ORDER BY credit_balance DESC, name ASC";
$stmt = $pdo->prepare($cQuery);
$stmt->execute($params);
$customerList = $stmt->fetchAll();

// Total Market Udhaar
$totalUdhaar = 0;
try {
    $tot = $pdo->query("SELECT SUM(credit_balance) as tot FROM customers")->fetch();
    $totalUdhaar = (float)($tot['tot'] ?? 0);
} catch (Exception $e) {}

// Selected Customer for Ledger View
$selectedCustId = (int)($_GET['view'] ?? 0);
$selectedCust = null;
$ledgerHistory = [];
if ($selectedCustId > 0) {
    $sC = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $sC->execute([$selectedCustId]);
    $selectedCust = $sC->fetch();

    if ($selectedCust) {
        $lH = $pdo->prepare("SELECT * FROM customer_ledgers WHERE customer_id = ? ORDER BY id DESC");
        $lH->execute([$selectedCustId]);
        $ledgerHistory = $lH->fetchAll();
    }
}
?>

<div class="flex-1 p-8 w-full overflow-y-auto bg-slate-50">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Customer Khata & Credit Ledger</h1>
            <p class="text-xs text-slate-500 mt-1">Track outstanding customer balances, record payments (vasooli), and view digital statements.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="openCustomerModal()" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm">
                + Register Customer
            </button>
        </div>
    </div>

    <!-- Outstanding Balance Banner (Clean Light Style) -->
    <div class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider block">Total Outstanding Market Udhaar (Receivable)</span>
            <div class="text-3xl font-black text-amber-700 font-mono mt-1">
                <?php echo $currency; ?> <?php echo number_format($totalUdhaar); ?>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="customers.php?udhaar_only=1" class="px-4 py-2 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold transition">
                Filter Active Udhaar Accounts
            </a>
            <?php if ($filterUdhaarOnly): ?>
                <a href="customers.php" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold transition">
                    Show All
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl text-xs font-semibold flex items-center gap-2 <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Customers List Column -->
        <div class="lg:col-span-2 rounded-xl bg-white border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <form method="GET" class="flex-1">
                    <input 
                        type="text" 
                        name="q" 
                        value="<?php echo htmlspecialchars($search); ?>" 
                        placeholder="Search by customer name or phone..." 
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600 font-medium"
                    >
                </form>
                <span class="text-xs text-slate-500 font-medium shrink-0"><?php echo count($customerList); ?> customers</span>
            </div>

            <div class="overflow-x-auto max-h-[550px]">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                            <th class="py-3.5 px-5">Customer Details</th>
                            <th class="py-3.5 px-5">Phone</th>
                            <th class="py-3.5 px-5">Udhaar Balance</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($customerList as $cust): ?>
                            <?php $bal = (float)$cust['credit_balance']; ?>
                            <tr class="hover:bg-slate-50/80 transition <?php echo ($selectedCustId === (int)$cust['id']) ? 'bg-slate-50' : ''; ?>">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($cust['name']); ?></div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[220px]"><?php echo htmlspecialchars($cust['address'] ?: 'No address'); ?></div>
                                </td>
                                <td class="py-4 px-5 font-mono text-slate-600 font-medium">
                                    <?php echo htmlspecialchars($cust['phone'] ?: '—'); ?>
                                </td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold font-mono <?php echo $bal > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200 font-bold' : 'bg-slate-100 text-slate-600'; ?>">
                                        <?php echo $currency . ' ' . number_format($bal); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="customers.php?view=<?php echo $cust['id']; ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition">
                                            Statement
                                        </a>
                                        <?php if ($bal > 0): ?>
                                            <button type="button" onclick="openVasooliModal(<?php echo $cust['id']; ?>, '<?php echo htmlspecialchars(addslashes($cust['name'])); ?>', <?php echo $bal; ?>)" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-bold transition">
                                                Vasooli
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Selected Customer Statement Column -->
        <div class="rounded-xl bg-white border border-slate-200 p-6 flex flex-col justify-between shadow-sm">
            <?php if ($selectedCust): ?>
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div>
                            <h2 class="font-bold text-slate-900 text-base leading-tight"><?php echo htmlspecialchars($selectedCust['name']); ?></h2>
                            <p class="text-xs text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($selectedCust['phone']); ?></p>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] uppercase font-bold text-slate-400 block">Balance</span>
                            <span class="text-lg font-black text-amber-700 font-mono"><?php echo $currency . ' ' . number_format((float)$selectedCust['credit_balance']); ?></span>
                        </div>
                    </div>

                    <h3 class="text-xs uppercase font-bold text-slate-600 tracking-wider mb-3">Ledger Entries</h3>
                    <div class="space-y-2.5 max-h-[360px] overflow-y-auto pr-1">
                        <?php if (empty($ledgerHistory)): ?>
                            <p class="text-xs text-slate-400 py-8 text-center font-medium">No ledger entries recorded yet.</p>
                        <?php else: ?>
                            <?php foreach ($ledgerHistory as $l): ?>
                                <?php $isCredit = ($l['type'] === 'credit_sale'); ?>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold <?php echo $isCredit ? 'text-amber-800' : 'text-emerald-700'; ?>">
                                            <?php echo $isCredit ? 'Credit Sale' : 'Payment Received'; ?>
                                        </span>
                                        <span class="font-mono font-bold <?php echo $isCredit ? 'text-amber-800' : 'text-emerald-700'; ?>">
                                            <?php echo ($isCredit ? '+' : '-') . $currency . ' ' . number_format((float)$l['amount']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                                        <span><?php echo htmlspecialchars($l['notes'] ?: ''); ?></span>
                                        <span class="font-mono">Bal: <?php echo $currency . ' ' . number_format((float)$l['balance_after']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 mt-4">
                    <button type="button" onclick="openVasooliModal(<?php echo $selectedCust['id']; ?>, '<?php echo htmlspecialchars(addslashes($selectedCust['name'])); ?>', <?php echo (float)$selectedCust['credit_balance']; ?>)" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                        Receive Payment (Vasooli)
                    </button>
                </div>
            <?php else: ?>
                <div class="py-28 text-center text-slate-400 my-auto">
                    <p class="text-sm font-semibold text-slate-600">Select customer</p>
                    <p class="text-xs text-slate-400 mt-1">To view statement ledger and record vasooli.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Modal: Receive Payment (Vasooli) -->
<div id="vasooli-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-sm w-full rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="font-bold text-sm text-slate-900">Record Vasooli</h3>
            <button type="button" onclick="closeVasooliModal()" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="customer_id" id="vasooli-customer-id" value="">

            <div>
                <span class="text-xs text-slate-600 block mb-0.5">Customer: <b id="vasooli-cust-name" class="text-slate-900 font-semibold"></b></span>
                <span class="text-xs text-slate-600 block">Current Balance: <b id="vasooli-cust-bal" class="text-amber-700 font-mono font-bold"></b></span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Amount Received *</label>
                <input type="number" step="0.01" name="amount" id="vasooli-amount" required placeholder="0.00" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold text-sm focus:outline-none focus:border-emerald-600">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Notes</label>
                <input type="text" name="notes" placeholder="e.g. Cash payment at counter" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeVasooliModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" name="receive_payment" value="1" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                    Confirm Vasooli
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add / Edit Customer -->
<div id="customer-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 max-w-md w-full rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 id="cust-modal-title" class="font-bold text-sm text-slate-900">Register Customer</h3>
            <button type="button" onclick="closeCustomerModal()" class="text-slate-400 hover:text-slate-900 font-bold text-lg">&times;</button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="customer_id" id="modal-cust-id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name *</label>
                <input type="text" name="name" id="modal-c-name" required placeholder="e.g. Tariq Mehmood" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number *</label>
                    <input type="text" name="phone" id="modal-c-phone" required placeholder="0300-1234567" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" id="modal-c-email" placeholder="customer@mail.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Address / Shop</label>
                <input type="text" name="address" id="modal-c-address" placeholder="Lahore" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 text-xs focus:outline-none focus:border-emerald-600">
            </div>

            <div id="initial-bal-wrapper">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Opening Balance (Udhaar)</label>
                <input type="number" step="0.01" name="initial_balance" value="0.00" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-600 font-semibold">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeCustomerModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" name="save_customer" value="1" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                    Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVasooliModal(id, name, bal) {
    document.getElementById('vasooli-customer-id').value = id;
    document.getElementById('vasooli-cust-name').innerText = name;
    document.getElementById('vasooli-cust-bal').innerText = `<?php echo $currency; ?> ${bal.toLocaleString()}`;
    document.getElementById('vasooli-amount').value = '';
    document.getElementById('vasooli-modal').classList.remove('hidden');
}

function closeVasooliModal() {
    document.getElementById('vasooli-modal').classList.add('hidden');
}

function openCustomerModal() {
    document.getElementById('cust-modal-title').innerText = 'Register Customer';
    document.getElementById('modal-cust-id').value = '';
    document.getElementById('modal-c-name').value = '';
    document.getElementById('modal-c-phone').value = '';
    document.getElementById('modal-c-email').value = '';
    document.getElementById('modal-c-address').value = '';
    document.getElementById('initial-bal-wrapper').classList.remove('hidden');
    document.getElementById('customer-modal').classList.remove('hidden');
}

function closeCustomerModal() {
    document.getElementById('customer-modal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
