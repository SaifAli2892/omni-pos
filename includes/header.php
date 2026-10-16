<?php
/**
 * OmniPOS - Professional Clean White Header
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$storeName   = getSetting('store_name', 'Omni Retail Mart');
$cloudToken  = getSetting('cloud_access_token', '');
$currency    = getSetting('currency_symbol', 'Rs.');
$user        = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($storeName); ?> | OmniPOS</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pos.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            600: '#059669',
                            700: '#047857',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="px-6 py-3 flex items-center justify-between gap-4 w-full">
            
            <!-- Left: Brand Logo & Title -->
            <div class="flex items-center gap-3 shrink-0">
                <a href="index.php" class="flex items-center gap-2">
                    <span class="font-black text-slate-950 text-xl tracking-tight">OMNI<span class="text-emerald-600">POS</span></span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Local Ready
                    </span>
                </a>
            </div>

            <!-- Middle: Main Navigation Tabs (Text Only, No Icons, Clean SaaS Style) -->
            <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs font-semibold">
                <a href="index.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'index.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    POS Terminal
                </a>
                <a href="products.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'products.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Inventory
                </a>
                <a href="categories.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'categories.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Categories
                </a>
                <a href="sales.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'sales.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Sales Registry
                </a>
                <a href="customers.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'customers.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Khata / Udhaar
                </a>
                <a href="dashboard.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'dashboard.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Reports
                </a>
                <a href="settings.php" class="px-4 py-2 rounded-lg transition <?php echo $currentPage === 'settings.php' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'; ?>">
                    Settings
                </a>
            </nav>

            <!-- Right: Cashier Identity & Time -->
            <div class="flex items-center gap-3">
                <div class="text-right text-xs">
                    <div class="font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($storeName); ?></div>
                    <div class="text-[11px] text-slate-500 capitalize"><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</div>
                </div>
            </div>

        </div>
    </header>

    <!-- Main Full-Screen Content -->
    <main class="flex-1 flex flex-col w-full">
