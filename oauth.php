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

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Step 1: Merchant Connect / Re-Authorize Trigger
if (empty($code) && (isset($_GET['connect']) || isset($_GET['install']) || isset($_GET['shop']) || !empty($_GET['installation_id']) || !empty($_GET['reauth']))) {
    $targetStoreId = !empty($_GET['store_id']) ? (string)$_GET['store_id'] : currentStoreId();
    if (!empty($targetStoreId)) {
        $_SESSION['oauth_requested_store_id'] = $targetStoreId;
        $_SESSION['scora_store_id'] = $targetStoreId;
    }
    if (!empty($_GET['installation_id'])) {
        setSetting('scora_installation_id', (string)$_GET['installation_id']);
    }

    $isForce = isset($_GET['force']) || !empty($_GET['installation_id']);

    // If not forced and no installation_id, show the clean "App Not Installed on Scora" guidance page
    if (!$isForce) {
        $storeName = $_SESSION['scora_store_name'] ?? ('Store ' . $targetStoreId);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Install OmniPOS on Scora</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Plus Jakarta Sans', sans-serif; }
            </style>
        </head>
        <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 text-slate-800">
            <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-xl border border-slate-200/80 text-center animate-in fade-in zoom-in-95 duration-200">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm">
                    🔌
                </div>
                <span class="inline-block px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 mb-3">
                    Scora App Installation Required
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 mb-2">OmniPOS is not installed on Scora yet</h1>
                <p class="text-xs text-slate-500 leading-relaxed mb-6">
                    Aapke Scora store (<b><?php echo htmlspecialchars($storeName); ?></b>) par abhi OmniPOS app install nahi hai. Pehle Scora Admin par ja kar app install karein:
                </p>

                <div class="bg-slate-50 rounded-2xl p-4 text-left border border-slate-200 mb-6 text-xs text-slate-600 space-y-2.5">
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[11px] shrink-0">1</span>
                        <span>Neeche <b>"Open Scora Apps Store"</b> par click karein.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[11px] shrink-0">2</span>
                        <span><b>OmniPOS</b> app ke saamne <b>Install App</b> par click karein.</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[11px] shrink-0">3</span>
                        <span>Permissions approve karein, aapka store automatically live sync ho jayega!</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <a href="https://scora.com.pk/admin/apps.php" target="_blank" class="w-full py-3.5 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs transition shadow-md flex items-center justify-center gap-2">
                        <span>🛍️ Open Scora Apps Store (apps.php)</span>
                    </a>
                    <a href="oauth.php?connect=1&store_id=<?php echo urlencode($targetStoreId); ?>&force=1" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <span>🔑 Already Installed? Proceed to Authorize</span>
                    </a>
                    <a href="index.php?store_id=<?php echo urlencode($targetStoreId); ?>" class="inline-block text-xs text-slate-400 hover:text-slate-600 font-semibold pt-1">
                        ← Back to POS Terminal
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

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
        $refreshToken = $tokenData['refresh_token'] ?? null;

        // Auto-detect store ID and name from Scora GraphQL
        $client = new CloudSyncClient($pdo);
        $shopInfo = $client->detectShopDetails($token);
        
        $detectedStoreId   = $shopInfo['store_id'] ?? $_SESSION['oauth_requested_store_id'] ?? currentStoreId();
        $detectedStoreName = $shopInfo['store_name'] ?? ('Store ' . $detectedStoreId);

        // Store token strictly isolated for this store
        setStoreAccessToken($detectedStoreId, $token, $refreshToken, $detectedStoreName);
        $_SESSION['scora_store_id']   = $detectedStoreId;
        $_SESSION['scora_store_name'] = $detectedStoreName;

        // Pull full live store catalog via GraphQL for this store
        $syncRes = $client->importStoreProducts($detectedStoreId);

        header('X-Frame-Options: ALLOWALL');
        header("Content-Security-Policy: frame-ancestors 'self' https://*.scora.com.pk https://scora.com.pk http://localhost:*");
        header("Location: index.php?store_id=" . urlencode($detectedStoreId) . "&live_synced=1&count=" . ($syncRes['imported'] ?? 0));
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
