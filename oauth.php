<?php
/**
 * OmniPOS - OAuth 2.0 & Auto-Install Onboarding Handler
 * When merchant installs on Scora, this automatically captures store details,
 * auto-syncs their products, and opens the POS terminal ready to use.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/sync.php';

$appId        = getSetting('cloud_client_id', '11');
$clientSecret = getSetting('cloud_client_secret', '9fbddbbdeb2f98337ef2b94513af72a0945eaa1a5bcfba5f95437875842de1f9');

$storeDomain = $_GET['store_domain'] ?? $_GET['shop'] ?? '';
$storeName   = $_GET['store_name'] ?? (!empty($storeDomain) ? ucfirst(explode('.', $storeDomain)[0]) : 'My Online Store');
$installId   = $_GET['installation_id'] ?? '';
$code        = $_GET['code'] ?? '';

// If installation is initiated directly (e.g. from POS website / store URL)
if (isset($_GET['install']) && !empty($storeDomain)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'saifali.alwaysdata.net';
    $redirectUri = urlencode($protocol . $host . '/oauth.php');
    $scopes = urlencode('products:read,products:write,inventory:read,inventory:write,orders:read,orders:write');
    $authUrl = "https://{$storeDomain}/admin/oauth/authorize?client_id={$appId}&scope={$scopes}&redirect_uri={$redirectUri}";
    header("Location: {$authUrl}");
    exit;
}

$client = new CloudSyncClient($pdo);

if (!empty($storeDomain)) {
    $accessToken = $code;

    // Exchange authorization code for access token if code is present
    if (!empty($code) && !empty($clientSecret)) {
        $tokenUrl = "https://{$storeDomain}/admin/oauth/access_token";
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id'     => $appId,
                'client_secret' => $clientSecret,
                'code'          => $code
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resp) {
            $data = json_decode($resp, true);
            if (!empty($data['access_token'])) {
                $accessToken = $data['access_token'];
            }
        }
    }

    // 1. Auto-onboard the store and pull products automatically
    $client->autoSyncStore($storeName, $storeDomain, $accessToken);

    // 2. Allow iframe embedding inside Scora Admin
    header('X-Frame-Options: ALLOWALL');
    header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk https://scora.com.pk http://localhost:*");

    header('Location: index.php?auto_synced=1');
    exit;
}

// Fallback direct open
header('X-Frame-Options: ALLOWALL');
header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk https://scora.com.pk http://localhost:*");
header('Location: index.php');
exit;

