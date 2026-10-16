<?php
/**
 * OmniPOS - OAuth 2.0 & Auto-Install Onboarding Handler
 * When merchant installs on Scora, this automatically captures store details,
 * auto-syncs their products, and opens the POS terminal ready to use.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/sync.php';

$storeDomain = $_GET['store_domain'] ?? $_GET['shop'] ?? '';
$storeName   = $_GET['store_name'] ?? (!empty($storeDomain) ? ucfirst(explode('.', $storeDomain)[0]) : 'My Online Store');
$installId   = $_GET['installation_id'] ?? '';
$code        = $_GET['code'] ?? '';

// If installation is initiated directly (e.g. from POS website / store URL)
if (isset($_GET['install']) && !empty($storeDomain)) {
    $clientId = getSetting('cloud_client_id', 'omnipos_app');
    $redirectUri = urlencode('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . '/oauth.php');
    $scopes = urlencode('read_products,write_products,read_inventory,write_inventory,read_orders,write_orders');
    $authUrl = "https://{$storeDomain}/admin/oauth/authorize?client_id={$clientId}&scope={$scopes}&redirect_uri={$redirectUri}";
    header("Location: {$authUrl}");
    exit;
}

$client = new CloudSyncClient($pdo);

if (!empty($storeDomain)) {
    // 1. Auto-onboard the store and pull products automatically!
    $client->autoSyncStore($storeName, $storeDomain, $code);

    // 2. Allow iframe embedding inside Scora Admin
    header('X-Frame-Options: ALLOWALL');
    header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk http://localhost:*");

    header('Location: index.php?auto_synced=1');
    exit;
}

// Fallback direct open
header('X-Frame-Options: ALLOWALL');
header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk http://localhost:*");
header('Location: index.php');
exit;

