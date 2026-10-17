/**
 * Scora POS - Frontend Terminal Logic
 * Fast Cart, Barcode Scanner, Web Audio Beep, Calculations & Thermal Receipt
 */

let cart = [];
let currencySymbol = window.storeCurrencySymbol || '$';

// Synthesize pleasant Scanner Beep using Web Audio API
function playScanBeep(type = 'success') {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(1200, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.08);
        } else {
            osc.frequency.setValueAtTime(350, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.15);
        }
    } catch (e) {
        // Fallback if browser audio context is blocked
    }
}

// Add item to cart
window.addToCart = function(product, qtyToAdd = 1) {
    const existing = cart.find(item => item.id === product.id);
    const currentStock = parseInt(product.stock_qty || 0);

    if (currentStock <= 0) {
        window.showToast(`"${product.title}" is out of stock!`, 'error');
        playScanBeep('error');
        return;
    }

    if (existing) {
        if (existing.qty + qtyToAdd > currentStock) {
            window.showToast(`Cannot add more than available stock (${currentStock})!`, 'error');
            playScanBeep('error');
            return;
        }
        existing.qty += qtyToAdd;
    } else {
        cart.push({
            id: product.id,
            title: product.title,
            barcode: product.barcode,
            sku: product.sku,
            price: parseFloat(product.selling_price),
            cost_price: parseFloat(product.cost_price || 0),
            qty: qtyToAdd,
            stock_qty: currentStock,
            scora_product_id: product.scora_product_id || null,
            scora_variant_id: product.scora_variant_id || null
        });
    }

    playScanBeep('success');
    renderCart();
};

// Update item quantity
window.updateQty = function(productId, delta) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    const newQty = item.qty + delta;
    if (newQty <= 0) {
        window.removeFromCart(productId);
        return;
    }

    if (newQty > item.stock_qty) {
        window.showToast(`Max stock is ${item.stock_qty}!`, 'error');
        return;
    }

    item.qty = newQty;
    renderCart();
};

// Remove single item from cart
window.removeFromCart = function(productId) {
    cart = cart.filter(i => i.id !== productId);
    renderCart();
};

// Clear entire cart
window.clearCart = function() {
    if (cart.length === 0) return;
    if (confirm('Clear current cart?')) {
        cart = [];
        renderCart();
    }
};

// Calculate totals, discounts, taxes
function calculateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discountInput = document.getElementById('discount-input');
    let discountVal = parseFloat(discountInput ? discountInput.value : 0) || 0;

    // Check if discount is entered as percentage e.g. "10%"
    let discountAmount = discountVal;
    if (discountInput && discountInput.dataset.type === 'percent') {
        discountAmount = (subtotal * discountVal) / 100;
    }
    discountAmount = Math.min(discountAmount, subtotal);

    const taxRate = parseFloat(window.storeTaxRate || 0);
    const taxAmount = ((subtotal - discountAmount) * taxRate) / 100;
    const grandTotal = Math.max(0, subtotal - discountAmount + taxAmount);

    const paidInput = document.getElementById('paid-amount-input');
    const paidVal = parseFloat(paidInput ? paidInput.value : 0) || grandTotal;
    const changeDue = Math.max(0, paidVal - grandTotal);

    return {
        subtotal,
        discount: discountAmount,
        tax: taxAmount,
        grandTotal,
        paid: paidVal,
        change: changeDue
    };
}

// Render Cart in DOM
function renderCart() {
    const listEl = document.getElementById('cart-items-list');
    const emptyEl = document.getElementById('cart-empty-state');
    const countBadge = document.getElementById('cart-count-badge');
    
    if (!listEl) return;

    if (window.storeCurrencySymbol) currencySymbol = window.storeCurrencySymbol;
    const totals = calculateTotals();

    if (countBadge) {
        const totalItemsCount = cart.reduce((c, i) => c + i.qty, 0);
        countBadge.innerText = totalItemsCount;
    }

    if (cart.length === 0) {
        listEl.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('hidden');
    } else {
        if (emptyEl) emptyEl.classList.add('hidden');
        listEl.innerHTML = cart.map(item => `
            <div class="cart-row flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs gap-2">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-slate-900 truncate">${escapeHtml(item.title)}</div>
                    <div class="text-[11px] text-slate-500 font-mono">
                        ${currencySymbol} ${item.price.toLocaleString()} × ${item.qty} = <span class="text-emerald-700 font-bold">${currencySymbol} ${(item.price * item.qty).toLocaleString()}</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" onclick="window.updateQty(${item.id}, -1)" class="w-7 h-7 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm transition">
                        -
                    </button>
                    <span class="w-7 text-center font-bold text-slate-900 font-mono text-sm">${item.qty}</span>
                    <button type="button" onclick="window.updateQty(${item.id}, 1)" class="w-7 h-7 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm transition">
                        +
                    </button>
                    <button type="button" onclick="window.removeFromCart(${item.id})" class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition ml-1" title="Remove">
                        ×
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Update summary labels
    const subtotalEl = document.getElementById('summary-subtotal');
    const grandTotalEl = document.getElementById('summary-grand-total');
    const changeDueEl = document.getElementById('summary-change-due');
    const paidInput = document.getElementById('paid-amount-input');

    if (subtotalEl) subtotalEl.innerText = `${currencySymbol} ${totals.subtotal.toLocaleString()}`;
    if (grandTotalEl) grandTotalEl.innerText = `${currencySymbol} ${totals.grandTotal.toLocaleString()}`;
    if (changeDueEl) changeDueEl.innerText = `${currencySymbol} ${totals.change.toLocaleString()}`;

    // Auto-fill paid amount with grand total if empty or user hasn't explicitly customized
    if (paidInput && (!paidInput.dataset.touched || paidInput.value === '')) {
        paidInput.value = totals.grandTotal > 0 ? totals.grandTotal : '';
        if (changeDueEl) changeDueEl.innerText = `${currencySymbol} 0`;
    }
}

// Quick cash tender buttons
window.setQuickTender = function(amount) {
    const paidInput = document.getElementById('paid-amount-input');
    const totals = calculateTotals();
    
    if (amount === 'exact') {
        paidInput.value = totals.grandTotal;
    } else {
        paidInput.value = amount;
    }
    paidInput.dataset.touched = 'true';
    renderCart();
};

// Process Checkout & Print
window.processCheckout = async function() {
    if (cart.length === 0) {
        window.showToast('Please add products to cart before checkout!', 'error');
        return;
    }

    const totals = calculateTotals();
    const customerSelect = document.getElementById('customer-select');
    const paymentSelect = document.getElementById('payment-method-select');
    const notesInput = document.getElementById('order-notes');

    const customerId = customerSelect ? customerSelect.value : 1;
    const paymentMethod = paymentSelect ? paymentSelect.value : 'cash';
    const notes = notesInput ? notesInput.value : '';

    const btn = document.getElementById('btn-checkout');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="animate-spin inline-block mr-2">⟳</span> Processing Sale...`;
    }

    try {
        const storeId = window.currentStoreId || new URLSearchParams(window.location.search).get('store_id') || '';
        const response = await fetch(`api/checkout.php?store_id=${encodeURIComponent(storeId)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                store_id: storeId,
                customer_id: customerId,
                payment_method: paymentMethod,
                subtotal: totals.subtotal,
                discount: totals.discount,
                tax_amount: totals.tax,
                total_amount: totals.grandTotal,
                paid_amount: totals.paid,
                items: cart,
                notes: notes
            })
        });

        const result = await response.json();

        if (result.success) {
            playScanBeep('success');
            window.lastSaleId = result.sale_id || null;
            window.showToast(`Invoice #${result.invoice_no} completed successfully!`, 'success');

            // Format thermal receipt
            formatThermalReceipt(result, cart, totals);

            // Open Receipt Modal
            openReceiptModal(result, cart, totals);

            // Update product stock counts in UI cards
            cart.forEach(item => {
                const badge = document.getElementById(`stock-badge-${item.id}`);
                if (badge) {
                    const remaining = Math.max(0, item.stock_qty - item.qty);
                    badge.innerText = `${remaining} in stock`;
                    if (remaining === 0) {
                        badge.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20';
                        badge.innerText = 'Sold Out';
                    }
                }
            });

            // Reset cart
            cart = [];
            const pInput = document.getElementById('paid-amount-input');
            if (pInput) {
                pInput.value = '';
                delete pInput.dataset.touched;
            }
            if (notesInput) notesInput.value = '';
            renderCart();

        } else {
            playScanBeep('error');
            window.showToast(result.error || 'Checkout failed!', 'error');
        }
    } catch (err) {
        console.error("Checkout error:", err);
        playScanBeep('error');
        window.showToast(err.message || 'Error during checkout!', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `Complete Sale & Print (F9)`;
        }
    }
};

// Format Receipt for Thermal Printer
function formatThermalReceipt(sale, items, totals) {
    const container = document.getElementById('thermal-receipt');
    if (!container) return;
    if (window.storeCurrencySymbol) currencySymbol = window.storeCurrencySymbol;

    const storeName = window.storeSettings?.name || 'Scora Retail Mart';
    const storeAddress = window.storeSettings?.address || 'Lahore, Pakistan';
    const storePhone = window.storeSettings?.phone || '0300-1234567';
    const footerText = window.storeSettings?.footer || 'Thank you for shopping with us!';

    let itemsHtml = items.map(item => `
        <tr>
            <td style="padding: 2px 0;">${escapeHtml(item.title)}<br><small>${item.qty} × ${currencySymbol} ${item.price.toLocaleString()}</small></td>
            <td style="text-align: right; vertical-align: bottom;">${currencySymbol} ${(item.qty * item.price).toLocaleString()}</td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div style="text-align: center; margin-bottom: 8px;">
            <h2 style="margin: 0; font-size: 16px; font-weight: bold;">${escapeHtml(storeName)}</h2>
            <p style="margin: 2px 0; font-size: 10px;">${escapeHtml(storeAddress)}</p>
            <p style="margin: 2px 0; font-size: 10px;">Phone: ${escapeHtml(storePhone)}</p>
            <p style="margin: 4px 0; border-bottom: 1px dashed #000;"></p>
        </div>

        <div style="font-size: 10px; margin-bottom: 6px;">
            <div><b>Invoice:</b> ${sale.invoice_no}</div>
            <div><b>Date:</b> ${sale.created_at}</div>
            <div><b>Customer:</b> ${escapeHtml(sale.customer_name)}</div>
            <div><b>Payment:</b> ${sale.payment_method.toUpperCase()}</div>
            ${sale.sync_status === 'synced' || sale.scora_sync_status === 'synced' ? '<div><b>Cloud Sync:</b> Verified ✓</div>' : ''}
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 8px;">
            <thead>
                <tr style="border-bottom: 1px dashed #000; border-top: 1px dashed #000;">
                    <th style="text-align: left; padding: 3px 0;">Item</th>
                    <th style="text-align: right; padding: 3px 0;">Amount</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>

        <div style="border-top: 1px dashed #000; padding-top: 4px; font-size: 11px;">
            <div style="display: flex; justify-content: space-between;"><span>Subtotal:</span><span>${currencySymbol} ${totals.subtotal.toLocaleString()}</span></div>
            ${totals.discount > 0 ? `<div style="display: flex; justify-content: space-between;"><span>Discount:</span><span>-${currencySymbol} ${totals.discount.toLocaleString()}</span></div>` : ''}
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; margin: 4px 0;"><span>TOTAL:</span><span>${currencySymbol} ${sale.total_amount.toLocaleString()}</span></div>
            <div style="display: flex; justify-content: space-between; font-size: 10px;"><span>Paid:</span><span>${currencySymbol} ${sale.paid_amount.toLocaleString()}</span></div>
            <div style="display: flex; justify-content: space-between; font-size: 10px;"><span>Change Due:</span><span>${currencySymbol} ${sale.change_amount.toLocaleString()}</span></div>
        </div>

        <div style="text-align: center; margin-top: 12px; font-size: 10px; border-top: 1px dashed #000; padding-top: 6px;">
            <p style="margin: 2px 0;">${escapeHtml(footerText)}</p>
            <p style="margin: 2px 0; font-size: 9px; color: #555;">Powered by OmniPOS</p>
        </div>
    `;
}

// Open Receipt Modal on Screen
function openReceiptModal(sale, items, totals) {
    const modal = document.getElementById('receipt-modal');
    const preview = document.getElementById('modal-receipt-preview');
    if (!modal || !preview) return;

    const receiptContent = document.getElementById('thermal-receipt').innerHTML;
    preview.innerHTML = receiptContent;
    modal.classList.remove('hidden');
}

window.closeReceiptModal = function() {
    const modal = document.getElementById('receipt-modal');
    if (modal) modal.classList.add('hidden');
};

window.printCurrentReceipt = function() {
    if (window.lastSaleId) {
        const win = window.open(`receipt.php?id=${window.lastSaleId}`, '_blank');
        if (win) {
            win.focus();
            return;
        }
    }

    const modalPreview = document.getElementById('modal-receipt-preview');
    const thermalReceipt = document.getElementById('thermal-receipt');
    const receiptHtml = (modalPreview && modalPreview.innerHTML.trim().length > 10) 
        ? modalPreview.innerHTML 
        : (thermalReceipt ? thermalReceipt.innerHTML : '');

    if (!receiptHtml) {
        window.print();
        return;
    }

    const fullPrintDoc = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt Print</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0mm;
        }
        @media print {
            html, body {
                width: 80mm;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        body {
            font-family: 'Courier New', Courier, 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.35;
            color: #000000;
            background: #ffffff;
            width: 76mm;
            margin: 0 auto;
            padding: 4mm 3mm;
            box-sizing: border-box;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { font-size: 11px; }
        * { box-sizing: border-box; }
    </style>
</head>
<body>
    ${receiptHtml}
</body>
</html>`;

    // Strategy 1: Dedicated clean print iframe (does not trigger popup blockers)
    let printFrame = document.getElementById('omni-pos-print-frame');
    if (!printFrame) {
        printFrame = document.createElement('iframe');
        printFrame.id = 'omni-pos-print-frame';
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        printFrame.style.visibility = 'hidden';
        document.body.appendChild(printFrame);
    }

    try {
        const frameDoc = printFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(fullPrintDoc);
        frameDoc.close();

        setTimeout(() => {
            try {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
            } catch (err) {
                fallbackPrintWindow(fullPrintDoc);
            }
        }, 250);
    } catch (e) {
        fallbackPrintWindow(fullPrintDoc);
    }
};

function fallbackPrintWindow(html) {
    const win = window.open('', '_blank', 'width=420,height=650');
    if (win) {
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(() => {
            win.print();
        }, 300);
    } else {
        const el = document.getElementById('thermal-receipt');
        if (el) el.classList.remove('hidden');
        window.print();
        if (el) el.classList.add('hidden');
    }
}

// Universal Barcode Scan Processor
async function handleBarcodeScanned(code) {
    if (!code) return;
    try {
        const res = await fetch(`api/products.php?action=search_or_scan&q=${encodeURIComponent(code)}`);
        const data = await res.json();

        if (data.success) {
            if (data.exact_barcode && data.product) {
                window.addToCart(data.product, 1);
                window.showToast(`Scanned: ${data.product.title}`, 'success');
                const searchInput = document.getElementById('product-search-input');
                if (searchInput) searchInput.value = '';
            } else if (data.products && data.products.length === 1) {
                window.addToCart(data.products[0], 1);
                window.showToast(`Scanned: ${data.products[0].title}`, 'success');
                const searchInput = document.getElementById('product-search-input');
                if (searchInput) searchInput.value = '';
            } else if (data.products && data.products.length > 1) {
                renderProductsGrid(data.products);
                window.showToast(`${data.products.length} products matched`, 'info');
            } else {
                playScanBeep('error');
                window.showToast(`Barcode "${code}" not found in inventory!`, 'error');
            }
        } else {
            playScanBeep('error');
            window.showToast(data.error || 'Barcode scan failed', 'error');
        }
    } catch (err) {
        console.error('Scan search failed:', err);
    }
}

// Barcode & Product Search Handler
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('product-search-input');
    const productsGrid = document.getElementById('products-grid');

    // Ensure search input is focused for immediate scanning
    if (searchInput) {
        searchInput.focus();

        // Search input Enter key handler
        searchInput.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (!query) return;
                handleBarcodeScanned(query);
            }
        });

        // Instant filter as user types manually
        searchInput.addEventListener('input', debounce(async () => {
            const query = searchInput.value.trim();
            if (query.length < 2 && query.length > 0) return;

            try {
                const res = await fetch(`api/products.php?action=search_or_scan&q=${encodeURIComponent(query)}`);
                const data = await res.json();
                if (data.success && data.products) {
                    renderProductsGrid(data.products);
                }
            } catch (err) {}
        }, 250));
    }

    // Global Hardware Barcode Scanner Wedge Listener
    // Catches rapid keystrokes from any USB / Bluetooth barcode scanner even when search input isn't focused
    let scanBuffer = '';
    let lastKeypressTime = 0;

    document.addEventListener('keydown', (e) => {
        const active = document.activeElement;
        const isExemptInput = active && (
            active.id === 'paid-amount-input' ||
            active.id === 'discount-input' ||
            active.id === 'new-cust-name' ||
            active.id === 'new-cust-phone' ||
            active.id === 'new-cust-address' ||
            active.tagName === 'TEXTAREA'
        );

        if (isExemptInput) return;

        const now = Date.now();
        const interval = now - lastKeypressTime;
        lastKeypressTime = now;

        // When scanner hits Enter at the end of the barcode
        if (e.key === 'Enter') {
            if (scanBuffer.length >= 3) {
                e.preventDefault();
                const codeToLookup = scanBuffer.trim();
                scanBuffer = '';
                handleBarcodeScanned(codeToLookup);
                return;
            }
            scanBuffer = '';
            return;
        }

        // Hardware scanners output characters rapidly (< 60ms intervals)
        if (e.key.length === 1) {
            if (interval > 80 && scanBuffer.length > 0) {
                // Slower typing indicates human, reset scanner buffer
                scanBuffer = '';
            }
            scanBuffer += e.key;
        }
    });


    // Category Filter Buttons
    document.querySelectorAll('.cat-pill').forEach(btn => {
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.cat-pill').forEach(b => {
                b.className = 'cat-pill px-4 py-2 rounded-lg font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 transition whitespace-nowrap shadow-sm';
            });
            btn.className = 'cat-pill px-4 py-2 rounded-lg font-bold bg-emerald-700 text-white border border-emerald-700 transition whitespace-nowrap shadow-sm';

            const catId = btn.dataset.category || 0;
            try {
                const res = await fetch(`api/products.php?action=by_category&category_id=${catId}`);
                const data = await res.json();
                if (data.success && data.products) {
                    renderProductsGrid(data.products);
                }
            } catch (err) {}
        });
    });

    // Keyboard Shortcuts (F2: Search, F9: Checkout, Escape: Close modal)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        } else if (e.key === 'F9') {
            e.preventDefault();
            window.processCheckout();
        } else if (e.key === 'Escape') {
            window.closeReceiptModal();
        }
    });

    // Initial render
    renderCart();
});

// Render Product Cards Grid
function renderProductsGrid(products) {
    const grid = document.getElementById('products-grid');
    if (!grid) return;

    if (products.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full py-16 text-center text-slate-400">
                <p class="text-base font-bold text-slate-700">No products in catalog</p>
                <p class="text-xs text-slate-500 mt-1">Add items using the Inventory page or import your store products.</p>
                <a href="products.php" class="inline-block mt-4 px-4 py-2 rounded-lg bg-emerald-700 text-white font-bold text-xs hover:bg-emerald-800 transition">
                    + Add Product
                </a>
            </div>
        `;
        return;
    }

    grid.innerHTML = products.map(p => {
        const stock = parseInt(p.stock_qty || 0);
        const isOutOfStock = stock <= 0;
        const jsonSafe = JSON.stringify(p).replace(/"/g, '&quot;');

        return `
            <div onclick="window.addToCart(${jsonSafe})" class="product-card group relative p-4 rounded-xl cursor-pointer flex flex-col justify-between ${isOutOfStock ? 'opacity-40 pointer-events-none' : ''}">
                <div>
                    <div class="flex items-center justify-between gap-1 mb-2.5">
                        <span class="text-xs font-mono text-slate-600 font-bold">${escapeHtml(p.barcode || p.sku || '')}</span>
                        <span id="stock-badge-${p.id}" class="text-[11px] px-2.5 py-0.5 rounded-full font-bold ${isOutOfStock ? 'bg-rose-50 text-rose-700 border border-rose-200' : (stock <= 5 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700')}">
                            ${isOutOfStock ? 'Sold Out' : `${stock} In Stock`}
                        </span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm leading-snug group-hover:text-emerald-700 transition mb-1">
                        ${escapeHtml(p.title)}
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">${escapeHtml(p.category_name || 'General')}</p>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="font-extrabold text-slate-900 font-sans text-base">
                        ${currencySymbol} ${parseFloat(p.selling_price).toLocaleString()}
                    </span>
                    <span class="px-3 py-1 rounded-md bg-emerald-50 text-emerald-700 group-hover:bg-emerald-700 group-hover:text-white font-bold text-xs transition">
                        Add +
                    </span>
                </div>
            </div>
        `;
    }).join('');
}

// Helpers
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Store Connection Redirect
window.quickAutoSyncStore = function() {
    window.location.href = 'settings.php';
};

