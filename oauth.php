<?php
/**
 * OmniPOS - Scora OAuth 2.0 PKCE & Real-Time Live Catalog Synchronizer
 * Connects directly to Scora Admin GraphQL API for real-time inventory and catalog syncing
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/sync.php';

$appId        = getSetting('cloud_client_id', '11');
$clientSecret = getSetting('cloud_client_secret', '9fbddbbdeb2f98337ef2b94513af72a0945eaa1a5bcfba5f95437875842de1f9');
$redirectUri  = 'https://saifali.alwaysdata.net/oauth.php';

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// Step 1: Merchant Connect / Re-Authorize Trigger
if (isset($_GET['connect']) || isset($_GET['install']) || (empty($code) && !empty($_GET['reauth']))) {
    // Generate PKCE verifier (32 bytes hex) and S256 challenge
    $verifier  = bin2hex(random_bytes(32));
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $randState = bin2hex(random_bytes(16));

    setSetting('scora_code_verifier', $verifier);
    setSetting('scora_oauth_state', $randState);

    $scopes = 'store:read products:read products:write collections:read orders:read orders:write customers:read customers:write inventory:read inventory:write';
    $installationId = getSetting('scora_installation_id', '11');

    $authorizeUrl = "https://scora.com.pk/api/admin/oauth/authorize?" . http_build_query([
        'client_id'             => $appId,
        'installation_id'       => $installationId,
        'redirect_uri'          => $redirectUri,
        'scope'                 => $scopes,
        'state'                 => $randState,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256'
    ]);

    header("Location: {$authorizeUrl}");
    exit;
}

// Step 2: Handle OAuth Callback from Scora
if (!empty($code)) {
    $verifier = getSetting('scora_code_verifier', '');

    $tokenPayload = [
        'grant_type'    => 'authorization_code',
        'client_id'     => (int)$appId,
        'client_secret' => $clientSecret,
        'code'          => $code,
        'redirect_uri'  => $redirectUri,
        'code_verifier' => $verifier
    ];

    $ch = curl_init('https://scora.com.pk/api/admin/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($tokenPayload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokenData = json_decode($resp, true);

    if ($httpCode === 200 && !empty($tokenData['access_token'])) {
        $token = $tokenData['access_token'];
        setSetting('cloud_access_token', $token);
        setSetting('cloud_local_mode', '0');
        setSetting('cloud_store_domain', 'scora.com.pk');
        if (!empty($tokenData['refresh_token'])) {
            setSetting('cloud_refresh_token', $tokenData['refresh_token']);
        }

        // Pull full live store catalog via GraphQL
        $client = new CloudSyncClient($pdo);
        $syncRes = $client->importStoreProducts();

        header('X-Frame-Options: ALLOWALL');
        header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk https://scora.com.pk http://localhost:*");
        header("Location: index.php?live_synced=1&count=" . ($syncRes['imported'] ?? 0));
        exit;
    } else {
        $errMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? $resp ?? 'Token exchange failed';
        setSetting('last_oauth_error', $errMsg);
        header("Location: index.php?oauth_error=" . urlencode($errMsg));
        exit;
    }
}

// Fallback direct open
header('X-Frame-Options: ALLOWALL');
header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk https://scora.com.pk http://localhost:*");
header('Location: index.php');
exit;
