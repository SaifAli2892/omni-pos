<?php
/**
 * Scora POS - 1-Click Database Installer & Setup Wizard
 */

require_once __DIR__ . '/config/database.php';

$message = '';
$status = 'info';

function runInstaller() {
    try {
        $pdoServer = getDBConnection(false);
        if (!$pdoServer) {
            return ['status' => 'error', 'message' => 'Could not connect to MySQL server. Check credentials in config/database.php'];
        }

        $sqlFile = __DIR__ . '/config/schema.sql';
        if (!file_exists($sqlFile)) {
            return ['status' => 'error', 'message' => 'schema.sql file not found in config/ directory.'];
        }

        $sql = file_get_contents($sqlFile);
        $pdoServer->exec($sql);

        return ['status' => 'success', 'message' => 'Database "omni_pos" and all tables have been successfully installed with sample products!'];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Installation error: ' . $e->getMessage()];
    }
}

// Check if running from CLI or HTTP
$isCli = (php_sapi_name() === 'cli');

if ($isCli || isset($_POST['install']) || isset($_GET['auto'])) {
    $result = runInstaller();
    if ($isCli) {
        echo "[" . strtoupper($result['status']) . "] " . $result['message'] . PHP_EOL;
        exit($result['status'] === 'success' ? 0 : 1);
    }
    $status = $result['status'];
    $message = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Install | OmniPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Manrope', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-slate-800 rounded-3xl p-8 border border-slate-700 shadow-2xl">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-2xl">
                ⚡
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-white">OmniPOS Setup</h1>
                <p class="text-xs text-slate-400">Universal Point of Sale for Retail & Online Stores</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="p-4 mb-6 rounded-2xl text-sm font-semibold <?php echo $status === 'success' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-red-500/10 text-red-300 border border-red-500/20'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php if ($status === 'success'): ?>
                <a href="index.php" class="w-full inline-flex items-center justify-center py-3.5 px-6 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold transition">
                    Open POS Terminal &rarr;
                </a>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-300 mb-6 leading-relaxed">
                Click the button below to initialize the MySQL database (<code class="text-emerald-400 bg-slate-950 px-1.5 py-0.5 rounded">omni_pos</code>), create all tables for billing, inventory, customers, udhaar ledger, and load sample products.
            </p>

            <form method="POST">
                <button type="submit" name="install" value="1" class="w-full py-4 px-6 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-base transition shadow-lg shadow-emerald-500/20">
                    Initialize Database & Sample Data
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 pt-6 border-t border-slate-700/60 text-xs text-slate-400 flex justify-between">
            <span>MySQL: 127.0.0.1:3306</span>
            <span>Default Admin: admin / admin123</span>
        </div>
    </div>
</body>
</html>
