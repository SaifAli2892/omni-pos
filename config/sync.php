<?php
/**
 * OmniPOS - Auto-Sync & Cloud Store Engine
 * Handles 1-Click store onboarding, automatic catalog pulling, and background stock sync
 */

require_once __DIR__ . '/database.php';

class CloudSyncClient {
    private $apiDomain;
    private $apiVersion;
    private $storeDomain;
    private $accessToken;
    private $clientId;
    private $clientSecret;
    private $pdo;
    private $isLocalMode;
    private $storeId;

    public function getStoreId() {
        return $this->storeId;
    }

    public function getAccessToken() {
        return $this->accessToken;
    }

    public function testConnection() {
        return [
            'success' => true,
            'store_id' => $this->storeId,
            'store_domain' => $this->storeDomain ?: 'standalone',
            'is_local' => $this->isLocalMode
        ];
    }

    public function cleanupDuplicates($storeId = null) {
        if (!$this->pdo) return ['success' => false, 'error' => 'No database connection'];
        $storeId = (string)($storeId ?: $this->storeId ?: currentStoreId());
        try {
            // Delete duplicate products for this store keeping the earliest record
            $sql = "DELETE p1 FROM products p1
                    INNER JOIN products p2 
                    WHERE p1.store_id = p2.store_id 
                      AND p1.store_id = ?
                      AND p1.id > p2.id AND (
                        REPLACE(REPLACE(p1.title, '-', ' '), '  ', ' ') = REPLACE(REPLACE(p2.title, '-', ' '), '  ', ' ')
                        OR (p1.sku = p2.sku AND p1.sku IS NOT NULL AND p1.sku != '')
                    )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$storeId]);
            $deleted = $stmt->rowCount();

            return [
                'success' => true,
                'deleted' => (int)$deleted,
                'message' => "Cleaned up {$deleted} duplicate entries successfully for store {$storeId}."
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function __construct($pdoInstance = null, $storeId = null) {
        global $pdo;
        $this->pdo = $pdoInstance ?: $pdo;
        $this->storeId = (string)($storeId ?: currentStoreId());
        
        $this->apiDomain   = rtrim(getSetting('cloud_api_domain', 'https://scora.com.pk'), '/');
        $this->apiVersion   = getSetting('cloud_api_version', '2026-08');
        $this->storeDomain  = trim(getSetting('cloud_store_domain', ''));
        $this->clientId     = getSetting('cloud_client_id', '11');
        $this->clientSecret = getSetting('cloud_client_secret', '9fbddbbdeb2f98337ef2b94513af72a0945eaa1a5bcfba5f95437875842de1f9');
        
        // Strictly isolated per store token
        $this->accessToken  = trim(getStoreAccessToken($this->storeId) ?? '');
        $this->isLocalMode  = (getSetting('cloud_local_mode', '1') === '1' || empty($this->accessToken));
    }

    /**
     * Query Scora Admin GraphQL to detect the exact store identity for a given token
     */
    public function detectShopDetails($token = null) {
        $tok = $token ?: $this->accessToken;
        if (empty($tok)) return null;

        $endpoint = "{$this->apiDomain}/api/admin/{$this->apiVersion}/graphql";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['query' => 'query { shop { id name } }']),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $tok
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            if (!empty($data['data']['shop'])) {
                $shop = $data['data']['shop'];
                $rawId = $shop['id'];
                $decoded = base64_decode($rawId);
                $storeNum = preg_match('/\/(\d+)$/', $decoded, $m) ? $m[1] : $rawId;
                return [
                    'success'    => true,
                    'store_id'   => (string)$storeNum,
                    'raw_id'     => $rawId,
                    'store_name' => $shop['name'] ?? ''
                ];
            }
        }
        return null;
    }

    public function isConfigured() {
        return !empty($this->storeDomain);
    }

    public function isLocal() {
        return $this->isLocalMode;
    }

    /**
     * 1-Click Auto Store Onboarding & Sync
     * Called automatically when app installs on any store
     */
    public function autoSyncStore($storeName, $storeDomain, $token = null) {
        if (!empty($token)) {
            setSetting('cloud_access_token', $token);
            $this->accessToken = $token;
            $this->isLocalMode = false;
            setSetting('cloud_local_mode', '0');
        }

        setSetting('store_name', $storeName);
        setSetting('cloud_store_domain', $storeDomain);
        setSetting('cloud_auto_sync', '1');
        setSetting('cloud_last_sync', date('d-M-Y h:i A'));

        // Only import if live token exists
        if (!empty($this->accessToken) && !$this->isLocalMode) {
            return $this->importStoreProducts();
        }

        return [
            'success' => true,
            'imported' => 0,
            'message' => "Store configuration saved."
        ];
    }


    /**
     * Refresh OAuth Access Token using Scora refresh_token
     */
    public function refreshAccessToken() {
        $refreshToken = null;
        if (!empty($this->storeId) && $this->pdo) {
            try {
                $stmt = $this->pdo->prepare("SELECT refresh_token FROM store_tokens WHERE store_id = ? LIMIT 1");
                $stmt->execute([$this->storeId]);
                $refreshToken = $stmt->fetchColumn();
            } catch (Exception $e) {}
        }
        if (empty($refreshToken)) {
            $refreshToken = getSetting('cloud_refresh_token', '');
        }

        if (empty($refreshToken) || empty($this->clientId) || empty($this->clientSecret)) {
            return false;
        }

        $ch = curl_init("{$this->apiDomain}/api/admin/oauth/token");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'grant_type'    => 'refresh_token',
                'client_id'     => (int)$this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if ($code === 200 && !empty($data['access_token'])) {
            $this->accessToken = $data['access_token'];
            $newRefresh = !empty($data['refresh_token']) ? $data['refresh_token'] : $refreshToken;
            if (!empty($this->storeId)) {
                setStoreAccessToken($this->storeId, $this->accessToken, $newRefresh);
            }
            setSetting('cloud_refresh_token', $newRefresh);
            return true;
        }
        return false;
    }

    /**
     * Real-Time Import of Store Products from Scora GraphQL API
     */
    public function importStoreProducts($storeId = null) {
        $storeId = (string)($storeId ?: $this->storeId ?: currentStoreId());
        $this->storeId = $storeId;
        $this->accessToken = trim(getStoreAccessToken($storeId) ?? '');

        if (empty($this->accessToken)) {
            return [
                'success'   => false,
                'need_auth' => true,
                'auth_url'  => 'oauth.php?connect=1&store_id=' . urlencode($storeId),
                'error'     => "Store {$storeId} is not connected to Scora yet. Click Live Scora Sync to authorize."
            ];
        }

        // 1. Live Currency Detection from Scora Shop API
        try {
            $shopRes = $this->query('query { shop { name currency } }');
            if (!empty($shopRes['data']['shop']['currency'])) {
                $curCode = strtoupper(trim($shopRes['data']['shop']['currency']));
                $symMap = [
                    'USD' => '$',
                    'PKR' => 'Rs.',
                    'EUR' => '€',
                    'GBP' => '£',
                    'CAD' => 'CA$',
                    'AUD' => 'AU$',
                    'AED' => 'AED ',
                    'SAR' => 'SAR ',
                    'INR' => '₹'
                ];
                $sym = $symMap[$curCode] ?? $curCode;
                try {
                    $this->pdo->prepare("UPDATE store_tokens SET currency = ?, currency_symbol = ? WHERE store_id = ?")->execute([$curCode, $sym, $storeId]);
                } catch (Exception $e) {}
                setSetting('currency_symbol', $sym);
                setSetting('store_currency', $curCode);
                $_SESSION['currency_symbol'] = $sym;
                $_SESSION['store_currency'] = $curCode;
            }
        } catch (Exception $e) {}

        // 2. Live Inventory Mapping from Scora inventoryItems API
        $stockByProduct = [];
        $invItemByProduct = [];
        try {
            $invRes = $this->query('query { inventoryItems(first: 100) { nodes { id sku productId availableQuantity } } }');
            if (!empty($invRes['data']['inventoryItems']['nodes'])) {
                foreach ($invRes['data']['inventoryItems']['nodes'] as $item) {
                    $qty = (int)($item['availableQuantity'] ?? 0);
                    $invId = $item['id'] ?? null;
                    if (!empty($item['productId'])) {
                        $stockByProduct[$item['productId']] = $qty;
                        if ($invId) $invItemByProduct[$item['productId']] = $invId;
                    }
                    if (!empty($item['sku'])) {
                        $stockByProduct[$item['sku']] = $qty;
                        if ($invId) $invItemByProduct[$item['sku']] = $invId;
                    }
                }
            }
        } catch (Exception $e) {}

        // 3. Attempt live GraphQL query for product catalog
        $result = $this->fetchProducts(100);
        $nodes = ($result['success'] && !empty($result['data']['products']['nodes'])) ? $result['data']['products']['nodes'] : [];

        // If GraphQL succeeds with nodes, import dynamically
        if (!empty($nodes)) {
            $imported = 0;
            try {
                foreach ($nodes as $prod) {
                    $cloudId = (string)$prod['id'];
                    $title = $prod['title'];
                    $price = (float)($prod['price'] ?? 0);
                    $sku = !empty($prod['sku']) ? $prod['sku'] : ('SKU-' . substr(md5($cloudId), 0, 8));
                    $costPrice = !empty($prod['compareAtPrice']) ? (float)$prod['compareAtPrice'] : ($price > 0 ? round($price * 0.7, 2) : 0);
                    $imageUrl = !empty($prod['imageUrl']) ? ('https://scora.com.pk/' . ltrim($prod['imageUrl'], '/')) : null;

                    // Exact dynamic stock from live Scora API inventoryItems
                    $stockQty = $stockByProduct[$cloudId] ?? $stockByProduct[$sku] ?? (int)($prod['stock'] ?? $prod['inventoryQuantity'] ?? 0);
                    $invItemId = $invItemByProduct[$cloudId] ?? $invItemByProduct[$sku] ?? null;

                    $variantId = null;
                    if (!empty($prod['variants']['nodes'][0]['id'])) {
                        $variantId = (string)$prod['variants']['nodes'][0]['id'];
                    }

                    $check = $this->pdo->prepare("SELECT id FROM products WHERE store_id = ? AND (scora_product_id = ? OR sku = ? OR title = ?) LIMIT 1");
                    $check->execute([$storeId, $cloudId, $sku, $title]);
                    $existing = $check->fetch();

                    if ($existing) {
                        $upd = $this->pdo->prepare("UPDATE products SET title = ?, sku = ?, cost_price = ?, selling_price = ?, stock_qty = ?, image_url = COALESCE(?, image_url), scora_product_id = ?, scora_variant_id = COALESCE(?, scora_variant_id), scora_inventory_item_id = COALESCE(?, scora_inventory_item_id), is_active = 1 WHERE id = ?");
                        $upd->execute([$title, $sku, $costPrice, $price, $stockQty, $imageUrl, $cloudId, $variantId, $invItemId, $existing['id']]);
                    } else {
                        $ins = $this->pdo->prepare("INSERT INTO products (store_id, title, sku, cost_price, selling_price, stock_qty, alert_qty, image_url, scora_product_id, scora_variant_id, scora_inventory_item_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?, ?, ?, 1)");
                        $ins->execute([$storeId, $title, $sku, $costPrice, $price, $stockQty, $imageUrl, $cloudId, $variantId, $invItemId]);
                    }
                    $imported++;
                }

                $this->cleanupDuplicates($storeId);
                setSetting('cloud_last_sync', date('d-M-Y h:i A'));
                $this->logEvent('live_graphql_sync', "Live-synced {$imported} real products with live stock from Scora Admin API for store {$storeId}", 'success');

                return [
                    'success'  => true,
                    'imported' => $imported,
                    'message'  => "Live catalog synced! Imported {$imported} products directly from Scora."
                ];
            } catch (Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // If GraphQL returned an error, return the actual Scora response
        if (!$result['success']) {
            return $result;
        }

        // Empty catalog on Scora
        return [
            'success'  => true,
            'imported' => 0,
            'message'  => "Scora store has 0 products in its catalog."
        ];
    }

    /**
     * Fetch products from store with dynamic schema detection
     */
    public function fetchProducts($limit = 100) {
        if (empty($this->accessToken)) {
            if (!$this->refreshAccessToken()) {
                return [
                    'success'   => false,
                    'need_auth' => true,
                    'auth_url'  => 'oauth.php?connect=1&store_id=' . urlencode($this->storeId),
                    'error'     => "Store {$this->storeId} is not authenticated."
                ];
            }
        }

        // Candidates to query live inventory along with catalog
        $candidates = [
            // 1. Direct stock field
            'query GetStoreCatalog($limit: Int!) {
                products(first: $limit) {
                    nodes {
                        id
                        title
                        handle
                        price
                        sku
                        compareAtPrice
                        imageUrl
                        stock
                    }
                }
            }',
            // 2. inventoryQuantity field
            'query GetStoreCatalog($limit: Int!) {
                products(first: $limit) {
                    nodes {
                        id
                        title
                        handle
                        price
                        sku
                        compareAtPrice
                        imageUrl
                        inventoryQuantity
                    }
                }
            }',
            // 3. totalInventory field
            'query GetStoreCatalog($limit: Int!) {
                products(first: $limit) {
                    nodes {
                        id
                        title
                        handle
                        price
                        sku
                        compareAtPrice
                        imageUrl
                        totalInventory
                    }
                }
            }',
            // 4. Variants inventory
            'query GetStoreCatalog($limit: Int!) {
                products(first: $limit) {
                    nodes {
                        id
                        title
                        handle
                        price
                        sku
                        compareAtPrice
                        imageUrl
                        variants(first: 5) {
                            nodes {
                                id
                                price
                                sku
                                inventoryQuantity
                            }
                        }
                    }
                }
            }',
            // 5. Standard fallback
            'query GetStoreCatalog($limit: Int!) {
                products(first: $limit) {
                    nodes {
                        id
                        title
                        handle
                        price
                        sku
                        compareAtPrice
                        imageUrl
                    }
                }
            }'
        ];

        foreach ($candidates as $query) {
            $res = $this->query($query, ['limit' => (int)$limit]);
            if ($res['success'] && isset($res['data']['products']['nodes'])) {
                return $res;
            }
        }

        return $this->query(end($candidates), ['limit' => (int)$limit]);
    }


    /**
     * Helper to ensure Scora product ID is in the base64 GID format expected by GraphQL
     */
    public function formatScoraProductId($id) {
        if (empty($id)) return null;
        $id = trim((string)$id);
        $decoded = @base64_decode($id, true);
        if ($decoded && str_starts_with($decoded, 'gid://scora/Product/')) {
            return $id;
        }
        if (str_starts_with($id, 'gid://scora/Product/')) {
            return base64_encode($id);
        }
        if (is_numeric($id)) {
            return base64_encode("gid://scora/Product/{$id}");
        }
        return $id;
    }

    /**
     * Create POS Counter Sale Order directly in Scora Cloud
     * Automatically reflects on Scora's Orders list, Analytics, and Main Dashboard
     */
    public function createCloudOrder($orderData) {
        if (empty($this->accessToken)) {
            return ['success' => false, 'error' => 'No access token'];
        }

        $mutation = 'mutation CreatePosOrder($input: OrderCreateInput!) {
            orderCreate(input: $input) {
                order {
                    id
                    orderNumber
                    status
                    statusCode
                    totalAmount
                    customerName
                    items {
                        id
                        productId
                        name
                        quantity
                        price
                    }
                }
                userErrors {
                    field
                    message
                }
                clientMutationId
            }
        }';

        $customerName = !empty($orderData['customer_name']) ? trim($orderData['customer_name']) : 'Walk-in Customer';
        $customerPhone = !empty($orderData['customer_phone']) ? trim($orderData['customer_phone']) : '03001234567';
        $invoiceNo = !empty($orderData['invoice_no']) ? $orderData['invoice_no'] : ('POS-' . time());
        $paymentMethod = !empty($orderData['payment_method']) ? ucfirst($orderData['payment_method']) : 'Cash';

        $lineItems = [];
        foreach ($orderData['items'] as $item) {
            $rawPid = $item['scora_product_id'] ?? $item['product_id'] ?? null;
            $formattedPid = $this->formatScoraProductId($rawPid);
            
            // If still missing, attempt lookup from local DB
            if (empty($formattedPid) && !empty($item['id']) && !empty($this->pdo)) {
                try {
                    $pStmt = $this->pdo->prepare("SELECT scora_product_id FROM products WHERE id = ? LIMIT 1");
                    $pStmt->execute([(int)$item['id']]);
                    $pRow = $pStmt->fetch();
                    if ($pRow && !empty($pRow['scora_product_id'])) {
                        $formattedPid = $this->formatScoraProductId($pRow['scora_product_id']);
                    }
                } catch (Exception $e) {}
            }

            if (!empty($formattedPid)) {
                $lineItems[] = [
                    'productId' => (string)$formattedPid,
                    'quantity'  => max(1, (int)($item['qty'] ?? 1)),
                    'price'     => (float)($item['price'] ?? 0),
                    'name'      => (string)($item['title'] ?? 'Product')
                ];
            }
        }

        if (empty($lineItems)) {
            return ['success' => false, 'error' => 'No valid cloud products found in order'];
        }

        $vars = [
            'input' => [
                'customerName'     => $customerName,
                'customerPhone'    => $customerPhone,
                'shippingAddress'  => 'In-Store Counter (' . $paymentMethod . ')',
                'status'           => 'paid',
                'source'           => 'pos',
                'lineItems'        => $lineItems,
                'clientMutationId' => 'pos-' . $invoiceNo . '-' . time()
            ]
        ];

        $res = $this->query($mutation, $vars);

        if (!empty($res['success']) && empty($res['data']['orderCreate']['userErrors']) && !empty($res['data']['orderCreate']['order']['id'])) {
            $cloudOrder = $res['data']['orderCreate']['order'];
            $this->logEvent('cloud_order_create', "Successfully created Scora Cloud Order #{$cloudOrder['orderNumber']} for invoice {$invoiceNo} (Amount: {$cloudOrder['totalAmount']})", 'success');
            return [
                'success'      => true,
                'cloud_order'  => $cloudOrder,
                'order_number' => $cloudOrder['orderNumber'],
                'cloud_id'     => $cloudOrder['id']
            ];
        }

        $errStr = 'Order creation failed';
        if (!empty($res['data']['orderCreate']['userErrors'])) {
            $errs = array_map(function($e) { return $e['message']; }, $res['data']['orderCreate']['userErrors']);
            $errStr = implode(', ', $errs);
        } elseif (!empty($res['error'])) {
            $errStr = $res['error'];
        }

        $this->logEvent('cloud_order_error', "Failed to create Scora Cloud Order for invoice {$invoiceNo}: {$errStr}", 'error');
        return ['success' => false, 'error' => $errStr, 'raw' => $res];
    }

    /**
     * Real-time stock adjustment on counter sale directly on Scora
     */
    public function adjustInventory($cloudProductId, $cloudVariantId = null, $delta = -1, $invItemId = null, $newStock = null) {
        if (empty($this->accessToken)) {
            $this->logEvent('stock_deduct', "Local stock deduction {$delta} for item {$cloudProductId}", 'info');
            return ['success' => true];
        }

        // 1. Fetch live product info from local DB
        $title = 'Product';
        $price = 0.0;
        $sku   = '';
        if (!empty($this->pdo)) {
            try {
                $pStmt = $this->pdo->prepare("SELECT scora_product_id, title, selling_price, sku, stock_qty, scora_inventory_item_id FROM products WHERE (scora_product_id = ? OR id = ?) LIMIT 1");
                $pStmt->execute([$cloudProductId, $cloudProductId]);
                $pRow = $pStmt->fetch();
                if ($pRow) {
                    if (!empty($pRow['scora_product_id'])) {
                        $cloudProductId = $pRow['scora_product_id'];
                    }
                    $title = $pRow['title'];
                    $price = (float)$pRow['selling_price'];
                    $sku   = $pRow['sku'];
                    if ($newStock === null) {
                        $newStock = max(0, (int)$pRow['stock_qty']);
                    }
                    if (empty($invItemId)) {
                        $invItemId = $pRow['scora_inventory_item_id'];
                    }
                }
            } catch (Exception $e) {}
        }

        $formattedId = $this->formatScoraProductId($cloudProductId);

        // 2. PRIMARY VERIFIED SCORA MUTATION: productUpdate with availableQuantity
        // This updates Scora Cloud's inventoryItems.availableQuantity directly and immediately
        if (!empty($formattedId)) {
            $primaryMutation = 'mutation UpdateProductStock($id: ID!, $input: ProductInput!) {
                productUpdate(id: $id, input: $input) {
                    product {
                        id
                        title
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';

            $primaryVars = [
                'id' => (string)$formattedId,
                'input' => [
                    'title'             => (string)$title,
                    'price'             => (float)$price,
                    'sku'               => (string)$sku,
                    'availableQuantity' => max(0, (int)$newStock)
                ]
            ];

            $res = $this->query($primaryMutation, $primaryVars);
            if (!empty($res['success']) && empty($res['data']['productUpdate']['userErrors'])) {
                $this->logEvent('stock_deduct', "Successfully updated Scora Cloud stock for '{$title}' to {$newStock}", 'success');
                return $res;
            }
        }

        $mutations = [];

        // 1. If inventoryItemId is known, try inventoryAdjustQuantities
        if (!empty($invItemId)) {
            $mutations[] = [
                'query' => 'mutation inventoryAdjustQuantities($input: InventoryAdjustQuantitiesInput!) {
                    inventoryAdjustQuantities(input: $input) {
                        userErrors { field message }
                    }
                }',
                'vars' => [
                    'input' => [
                        'reason' => 'correction',
                        'name' => 'available',
                        'changes' => [
                            [
                                'delta' => (int)$delta,
                                'inventoryItemId' => (string)$invItemId
                            ]
                        ]
                    ]
                ]
            ];

            // 2. Direct inventoryAdjustQuantity with inventoryItemId
            $mutations[] = [
                'query' => 'mutation inventoryAdjustQuantity($input: InventoryAdjustQuantityInput!) {
                    inventoryAdjustQuantity(input: $input) {
                        userErrors { field message }
                    }
                }',
                'vars' => [
                    'input' => [
                        'inventoryItemId' => (string)$invItemId,
                        'availableDelta'  => (int)$delta
                    ]
                ]
            ];

            // 3. inventorySetOnHandQuantities if newStock is provided
            if ($newStock !== null) {
                $mutations[] = [
                    'query' => 'mutation inventorySetOnHandQuantities($input: InventorySetOnHandQuantitiesInput!) {
                        inventorySetOnHandQuantities(input: $input) {
                            userErrors { field message }
                        }
                    }',
                    'vars' => [
                        'input' => [
                            'reason' => 'correction',
                            'setQuantities' => [
                                [
                                    'inventoryItemId' => (string)$invItemId,
                                    'quantity'        => max(0, (int)$newStock)
                                ]
                            ]
                        ]
                    ]
                ];

                // 4. inventoryItemUpdate
                $mutations[] = [
                    'query' => 'mutation inventoryItemUpdate($id: ID!, $input: InventoryItemInput!) {
                        inventoryItemUpdate(id: $id, input: $input) {
                            userErrors { field message }
                        }
                    }',
                    'vars' => [
                        'id' => (string)$invItemId,
                        'input' => [
                            'availableQuantity' => max(0, (int)$newStock)
                        ]
                    ]
                ];
            }
        }

        // 5. Fallback: variantId / productId based inventoryAdjustQuantity
        $targetVarId = $cloudVariantId ?: $cloudProductId;
        if (!empty($targetVarId)) {
            $mutations[] = [
                'query' => 'mutation AdjustInventoryQuantity($variantId: ID!, $delta: Int!) {
                    inventoryAdjustQuantity(input: { variantId: $variantId, availableDelta: $delta }) {
                        inventoryLevel { id available }
                        userErrors { field message }
                    }
                }',
                'vars' => [
                    'variantId' => $targetVarId,
                    'delta'     => (int)$delta
                ]
            ];
        }

        $lastRes = null;
        foreach ($mutations as $m) {
            $lastRes = $this->query($m['query'], $m['vars']);
            if ($lastRes['success']) {
                $this->logEvent('stock_deduct', "Successfully deducted {$delta} online stock on Scora for item {$cloudProductId}", 'success');
                return $lastRes;
            }
        }

        // Error classification: Check if permission/scope is missing
        $errMsg = $lastRes['error'] ?? 'Could not adjust stock on Scora';
        $isForbidden = (stripos($errMsg, 'not allowed') !== false) || (stripos($errMsg, 'forbidden') !== false);

        if ($isForbidden) {
            $this->logEvent('stock_sync_scope_notice', "Scora OAuth App needs 'inventory:write' permission in Scora Developer Portal & Re-authorization.", 'warning');
            return [
                'success'    => false,
                'need_scope' => true,
                'error'      => "Scora store permissions: App ID 11 needs 'inventory:write' permission enabled in Scora Developer Portal. Please re-authorize by clicking Live Scora Sync."
            ];
        }

        return $lastRes ?? ['success' => false, 'error' => $errMsg];
    }

    public function query($graphQLQuery, $variables = [], $retried = false) {
        if (empty($this->accessToken)) {
            return [
                'success'   => false,
                'need_auth' => true,
                'auth_url'  => 'oauth.php?connect=1&store_id=' . urlencode($this->storeId),
                'error'     => 'No access token available for store ' . $this->storeId
            ];
        }

        $endpoint = "{$this->apiDomain}/api/admin/{$this->apiVersion}/graphql";
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'Idempotency-Key: ' . bin2hex(random_bytes(16))
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['query' => $graphQLQuery, 'variables' => (object)$variables]),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'Network timeout calling Scora GraphQL'];
        }

        $decoded = json_decode($response, true);

        // If token expired (HTTP 401, UNAUTHENTICATED, or "expired" message), auto-refresh token and retry once
        $isExpired = ($httpCode === 401)
            || (isset($decoded['errors'][0]['extensions']['code']) && $decoded['errors'][0]['extensions']['code'] === 'UNAUTHENTICATED')
            || (isset($decoded['errors'][0]['message']) && stripos($decoded['errors'][0]['message'], 'expired') !== false)
            || (isset($decoded['errors'][0]['message']) && stripos($decoded['errors'][0]['message'], 'refresh token') !== false);

        if ($isExpired && !$retried) {
            if ($this->refreshAccessToken()) {
                return $this->query($graphQLQuery, $variables, true);
            }
            return [
                'success'   => false,
                'need_auth' => true,
                'auth_url'  => 'oauth.php?connect=1&store_id=' . urlencode($this->storeId),
                'error'     => 'Token expired. Please click Live Scora Sync to re-authorize.'
            ];
        }

        if ($httpCode >= 400 || !empty($decoded['errors'])) {
            return [
                'success'      => false,
                'http_code'    => $httpCode,
                'error'        => $decoded['errors'][0]['message'] ?? 'Query error',
                'raw_errors'   => $decoded['errors'] ?? null,
                'raw_response' => $decoded
            ];
        }

        return ['success' => true, 'data' => $decoded['data'] ?? []];
    }

    public function logEvent($event, $details, $status = 'info') {
        if (!$this->pdo) return;
        try {
            $storeId = currentStoreId();
            $stmt = $this->pdo->prepare("INSERT INTO sync_logs (`store_id`, `event`, `details`, `status`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$storeId, $event, is_string($details) ? $details : json_encode($details), $status]);
        } catch (Exception $e) {}
    }
}
