/* ============================================================
   Custom Popup + Alerts + Multi-Sale + Sale Detail
   ============================================================ */

(function () {
    'use strict';

    let pendingElement = null;
    let pendingType = null;

    const COLOR_PRESETS = [
        '#4f46e5', '#2563eb', '#0891b2', '#16a34a', '#65a30d', '#ca8a04',
        '#ea580c', '#dc2626', '#db2777', '#9333ea', '#475569', '#0f172a',
    ];

    function getMainContent() { return document.querySelector('.main-content'); }

    /* ---------- MOBILE MENU ---------- */
    function initMobileMenu() {
        const menuBtn = document.getElementById('mobileMenuBtn');
        const closeBtn = document.getElementById('mobileMenuClose');
        const overlay = document.getElementById('mobileMenuOverlay');
        const sidebar = document.getElementById('appSidebar');
        if (!menuBtn || !sidebar) return;
        function openMenu() {
            document.body.classList.add('menu-open');
            sidebar.classList.add('mobile-open');
            if (overlay) overlay.classList.add('show');
        }
        function closeMenu() {
            document.body.classList.remove('menu-open');
            sidebar.classList.remove('mobile-open');
            if (overlay) overlay.classList.remove('show');
        }
        menuBtn.addEventListener('click', openMenu);
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);
        sidebar.querySelectorAll('.nav a').forEach(link => {
            if (link.hasAttribute('data-confirm')) return;
            link.addEventListener('click', function () {
                if (window.innerWidth <= 860) closeMenu();
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) closeMenu();
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 860 && sidebar.classList.contains('mobile-open')) closeMenu();
        });
    }

    /* ---------- PAGE TRANSITIONS ---------- */
    function initPageTransitions() {
        const main = getMainContent();
        if (main) {
            main.classList.add('mc-enter');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    main.classList.remove('mc-enter');
                    main.classList.add('mc-active');
                });
            });
        }
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href) return;
            if (
                href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') ||
                href.startsWith('tel:') || link.target === '_blank' || link.hasAttribute('download') ||
                link.hasAttribute('data-popup-form') || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0
            ) return;
            if (link.hasAttribute('data-confirm')) return;
            try {
                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) return;
            } catch (_) { return; }
            const m = getMainContent();
            if (!m) { window.location.href = href; return; }
            e.preventDefault();
            m.classList.remove('mc-active');
            m.classList.add('mc-exit');
            setTimeout(function () { window.location.href = href; }, 280);
        });
    }

    /* ---------- CONFIRM MODAL ---------- */
    function injectModal() {
        if (document.getElementById('customModalOverlay')) return;
        const modalHTML = `
        <div class="modal-overlay" id="customModalOverlay">
            <div class="modal-box">
                <div class="modal-icon danger" id="modalIcon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h2 class="modal-title" id="modalTitle">Are you sure?</h2>
                <p class="modal-message" id="modalMessage">This action cannot be undone.</p>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-cancel" id="modalCancel"><i class="fa-solid fa-xmark"></i> Cancel</button>
                    <button type="button" class="modal-btn modal-confirm" id="modalConfirm"><i class="fa-solid fa-check"></i> Confirm</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function openModal(opts) {
        const overlay = document.getElementById('customModalOverlay');
        if (!overlay) { injectModal(); return openModal(opts); }
        document.getElementById('modalTitle').textContent = opts.title || 'Are you sure?';
        document.getElementById('modalMessage').textContent = opts.message || '';
        const iconEl = document.getElementById('modalIcon');
        iconEl.className = 'modal-icon ' + (opts.type || 'warning');
        iconEl.innerHTML = opts.icon || '<i class="fa-solid fa-triangle-exclamation"></i>';
        const okBtn = document.getElementById('modalConfirm');
        const cancelBtn = document.getElementById('modalCancel');
        okBtn.className = 'modal-btn ' + (opts.danger ? 'modal-danger' : 'modal-confirm');
        okBtn.innerHTML = (opts.danger ? '<i class="fa-solid fa-right-from-bracket"></i> ' : '<i class="fa-solid fa-check"></i> ') + (opts.confirmText || 'Confirm');
        cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> ' + (opts.cancelText || 'Cancel');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        const newOk = okBtn.cloneNode(true);
        const newCancel = cancelBtn.cloneNode(true);
        okBtn.replaceWith(newOk);
        cancelBtn.replaceWith(newCancel);

        newOk.addEventListener('click', function () {
            closeModal();
            if (pendingType === 'link' && pendingElement) {
                const target = pendingElement.href;
                const m = getMainContent();
                if (m) { m.classList.remove('mc-active'); m.classList.add('mc-exit'); setTimeout(function () { window.location.href = target; }, 280); }
                else window.location.href = target;
            } else if (pendingType === 'form' && pendingElement) {
                pendingElement.dataset.confirmed = '1';
                pendingElement.submit();
            }
            pendingElement = null;
            pendingType = null;
        });
        newCancel.addEventListener('click', function () { closeModal(); pendingElement = null; pendingType = null; });
        overlay.onclick = (e) => { if (e.target === overlay) { closeModal(); pendingElement = null; pendingType = null; } };
        document.onkeydown = (e) => { if (e.key === 'Escape') { closeModal(); pendingElement = null; pendingType = null; } };
    }

    function closeModal() {
        const overlay = document.getElementById('customModalOverlay');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
        document.onkeydown = null;
    }

    /* ---------- FORM POPUP ---------- */
    function injectFormModal() {
        if (document.getElementById('formPopupOverlay')) return;
        const html = `
        <div class="modal-overlay" id="formPopupOverlay">
            <div class="modal-box modal-box-form" id="formPopupBox">
                <div class="fp-header">
                    <div class="fp-title-wrap">
                        <div class="modal-icon info" id="fpIcon"><i class="fa-solid fa-plus"></i></div>
                        <div>
                            <h2 class="modal-title" id="fpTitle" style="text-align:left;margin:0;">Popup</h2>
                            <p class="modal-message" id="fpSubtitle" style="text-align:left;margin:4px 0 0;">Fill the form</p>
                        </div>
                    </div>
                    <button type="button" class="fp-close" id="fpClose"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <form id="fpForm" method="POST" autocomplete="off">
                    <div class="fp-body" id="fpBody"></div>
                    <div class="modal-actions" style="margin-top:8px;">
                        <button type="button" class="modal-btn modal-cancel" id="fpCancel"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        <button type="submit" class="modal-btn modal-confirm" id="fpSubmit"><i class="fa-solid fa-check"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    function openFormPopup(opts) {
        injectFormModal();
        const overlay = document.getElementById('formPopupOverlay');
        const form = document.getElementById('fpForm');
        const body = document.getElementById('fpBody');
        const submitBtn = document.getElementById('fpSubmit');

        document.getElementById('fpTitle').textContent = opts.title || 'Form';
        document.getElementById('fpSubtitle').textContent = opts.subtitle || '';
        const icon = document.getElementById('fpIcon');
        icon.className = 'modal-icon ' + (opts.iconType || 'info');
        icon.innerHTML = opts.icon || '<i class="fa-solid fa-plus"></i>';
        form.action = opts.action || '';
        form.method = opts.method || 'POST';
        form.noValidate = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> ' + (opts.submitText || 'Save');
        body.innerHTML = opts.body || '';

        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            const first = body.querySelector('input:not([readonly]):not([disabled]), select, textarea');
            if (first && first.offsetParent !== null) first.focus();
        }, 50);

        const close = () => { overlay.classList.remove('active'); document.body.style.overflow = ''; };
        document.getElementById('fpClose').onclick = close;
        document.getElementById('fpCancel').onclick = close;
        overlay.onclick = (e) => { if (e.target === overlay) close(); };
        document.onkeydown = (e) => { if (e.key === 'Escape') close(); };
    }

    /* ---------- HELPERS ---------- */
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]));
    }
    function maskText(str, frontPct = 0.10, backPct = 0.10) {
        str = String(str || '');
        if (str.length <= 2) return '•••';
        const front = Math.max(1, Math.ceil(str.length * frontPct));
        const back  = Math.max(1, Math.ceil(str.length * backPct));
        if (front + back >= str.length) return str.charAt(0) + '•••' + str.charAt(str.length - 1);
        return str.slice(0, front) + '•'.repeat(Math.max(3, str.length - front - back)) + str.slice(-back);
    }
    function isValidHex(c) { return /^#[0-9A-Fa-f]{6}$/.test(c); }
    function fmtRs(n) { return 'Rs ' + (parseFloat(n) || 0).toFixed(2); }

    /* ---------- COLOR PICKER ---------- */
    function buildColorPicker(currentColor, fieldName = 'color') {
        currentColor = isValidHex(currentColor) ? currentColor : '#4f46e5';
        let swatches = '';
        COLOR_PRESETS.forEach(c => {
            const selected = c.toLowerCase() === currentColor.toLowerCase() ? 'selected' : '';
            swatches += `<button type="button" class="color-swatch ${selected}" data-color="${c}" style="background:${c};" title="${c}"></button>`;
        });
        return `
            <label class="form-label">Profile Color</label>
            <div class="color-picker" data-color-picker>
                <div class="color-swatches">${swatches}</div>
                <div class="color-custom-row">
                    <input type="color" class="color-input-native" value="${currentColor}" data-color-native>
                    <input type="text" class="color-input-hex" name="${fieldName}" value="${currentColor}" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" data-color-hex>
                </div>
            </div>
        `;
    }
    function attachColorPickerLogic(container) {
        if (!container) return;
        const picker = container.querySelector('[data-color-picker]');
        if (!picker) return;
        const native = picker.querySelector('[data-color-native]');
        const hex = picker.querySelector('[data-color-hex]');
        const swatches = picker.querySelectorAll('.color-swatch');
        function setColor(c) {
            if (!isValidHex(c)) return;
            native.value = c; hex.value = c;
            swatches.forEach(s => s.classList.toggle('selected', s.dataset.color.toLowerCase() === c.toLowerCase()));
        }
        swatches.forEach(s => s.addEventListener('click', function () { setColor(this.dataset.color); }));
        native.addEventListener('input', function () { setColor(this.value); });
        hex.addEventListener('input', function () {
            const v = this.value.trim();
            if (isValidHex(v)) {
                native.value = v;
                swatches.forEach(s => s.classList.toggle('selected', s.dataset.color.toLowerCase() === v.toLowerCase()));
            }
        });
    }

    /* ---------- MULTI-SALE FORM ---------- */
    function buildMultiSaleForm(products, isAdminView) {
        const today = new Date().toISOString().split('T')[0];
        const dateField = isAdminView
            ? `<label class="form-label"><i class="fa-solid fa-calendar-days" style="color:var(--primary);margin-right:6px;"></i>Sale Date <span class="muted" style="font-weight:400;font-size:12px;">(admin only)</span></label>
               <div class="input-group">
                   <i class="fa-solid fa-calendar input-icon"></i>
                   <input type="date" name="sale_date" id="msSaleDate" value="${today}" required>
               </div>`
            : `<label class="form-label"><i class="fa-solid fa-calendar-days" style="color:var(--text-muted);margin-right:6px;"></i>Sale Date</label>
               <div class="input-group">
                   <i class="fa-solid fa-lock input-icon"></i>
                   <input type="date" value="${today}" readonly disabled
                          style="background:var(--surface-alt);color:var(--text-muted);cursor:not-allowed;">
                   <input type="hidden" name="sale_date" value="${today}">
               </div>`;

        return `
            <div class="multi-sale-wrap">
                ${dateField}

                <label class="form-label">Add Products</label>
                <div class="ms-search-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="msSearch" placeholder="Type to search product..." autocomplete="off">
                </div>
                <div class="ms-dropdown" id="msDropdown"></div>

                <div class="ms-selected" id="msSelected">
                    <p class="ms-empty" id="msEmpty">Abhi tak koi product select nahi kiya.</p>
                </div>

                <div class="ms-totals" id="msTotals" style="display:none;">
                    <div class="ms-total-row">
                        <span>Total Items</span>
                        <strong id="msTotalItems">0</strong>
                    </div>
                    <div class="ms-total-row">
                        <span>Total Quantity</span>
                        <strong id="msTotalQty">0</strong>
                    </div>
                    <div class="ms-total-row">
                        <span>Total Amount</span>
                        <strong id="msTotalAmount">Rs 0.00</strong>
                    </div>
                    <div class="ms-total-row" id="msPLRow" style="display:none;">
                        <span>Profit / Loss</span>
                        <strong id="msPL">Rs 0.00</strong>
                    </div>
                </div>

                <input type="hidden" name="sale_items" id="msItemsInput" value="[]">
            </div>
        `;
    }

    function attachMultiSaleLogic(products, isAdminView) {
        setTimeout(function () {
            const searchInput = document.getElementById('msSearch');
            const dropdown = document.getElementById('msDropdown');
            const selectedWrap = document.getElementById('msSelected');
            const totals = document.getElementById('msTotals');
            const itemsInput = document.getElementById('msItemsInput');
            const totalItemsEl = document.getElementById('msTotalItems');
            const totalQtyEl = document.getElementById('msTotalQty');
            const totalAmountEl = document.getElementById('msTotalAmount');
            const plRow = document.getElementById('msPLRow');
            const plEl = document.getElementById('msPL');

            if (!searchInput) return;

            let cart = [];

            function renderSelected() {
                if (cart.length === 0) {
                    selectedWrap.innerHTML = `<p class="ms-empty">Abhi tak koi product select nahi kiya.</p>`;
                    totals.style.display = 'none';
                    itemsInput.value = '[]';
                    return;
                }
                totals.style.display = 'block';
                let html = '';
                let tItems = 0, tQty = 0, tAmount = 0, tPL = 0;
                cart.forEach((item, idx) => {
                    const total = item.sale_price * item.quantity;
                    const pl = (item.sale_price - item.cost_price) * item.quantity;
                    tItems++; tQty += item.quantity; tAmount += total; tPL += pl;

                    html += `
                        <div class="ms-item">
                            <div class="ms-item-main">
                                <div class="ms-item-name">${escapeHtml(item.name)}</div>
                                <div class="ms-item-meta">Stock: ${item.stock} · Cost: Rs ${item.cost_price.toFixed(2)}</div>
                            </div>
                            <div class="ms-item-fields">
                                <div class="ms-mini-field">
                                    <label>Qty</label>
                                    <input type="number" min="1" max="${item.stock}" value="${item.quantity}" data-idx="${idx}" data-field="quantity">
                                </div>
                                <div class="ms-mini-field">
                                    <label>Price</label>
                                    <input type="number" min="0" step="0.01" value="${item.sale_price.toFixed(2)}" data-idx="${idx}" data-field="sale_price">
                                </div>
                                <button type="button" class="ms-remove" data-idx="${idx}" title="Remove">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                selectedWrap.innerHTML = html;

                totalItemsEl.textContent = tItems;
                totalQtyEl.textContent = tQty;
                totalAmountEl.textContent = fmtRs(tAmount);

                if (isAdminView) {
                    plRow.style.display = 'flex';
                    if (tPL > 0) { plEl.textContent = 'Profit ' + fmtRs(tPL); plEl.style.color = 'var(--success)'; }
                    else if (tPL < 0) { plEl.textContent = 'Loss ' + fmtRs(Math.abs(tPL)); plEl.style.color = 'var(--danger)'; }
                    else { plEl.textContent = 'No P/L'; plEl.style.color = 'var(--text-muted)'; }
                }

                itemsInput.value = JSON.stringify(cart.map(c => ({
                    product_id: c.product_id,
                    quantity: c.quantity,
                    sale_price: c.sale_price
                })));

                selectedWrap.querySelectorAll('input[data-field]').forEach(inp => {
                    inp.addEventListener('input', function () {
                        const idx = parseInt(this.dataset.idx);
                        const field = this.dataset.field;
                        let val = parseFloat(this.value);
                        if (isNaN(val) || val < 0) val = 0;
                        if (field === 'quantity') {
                            val = Math.min(Math.floor(val), cart[idx].stock);
                            if (val < 1) val = 1;
                        }
                        cart[idx][field] = val;
                        renderSelected();
                    });
                });
                selectedWrap.querySelectorAll('.ms-remove').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const idx = parseInt(this.dataset.idx);
                        cart.splice(idx, 1);
                        renderSelected();
                    });
                });
            }

            function showDropdown(query) {
                const q = (query || '').trim().toLowerCase();

                // ✅ FIX: Khaali query pe dropdown nahi khulega
                if (q === '') {
                    dropdown.classList.remove('show');
                    dropdown.innerHTML = '';
                    return;
                }

                const filtered = products
                    .filter(p => p.name.toLowerCase().includes(q))
                    .slice(0, 10);

                if (filtered.length === 0) {
                    dropdown.innerHTML = `<div class="ms-drop-empty">Koi product nahi mila</div>`;
                    dropdown.classList.add('show');
                    return;
                }
                let html = '';
                filtered.forEach(p => {
                    const already = cart.some(c => c.product_id == p.id);
                    const stock = parseInt(p.quantity);
                    const sellPrice = parseFloat(p.price);
                    const costPrice = parseFloat(p.cost_price || 0);
                    const outOfStock = stock <= 0;
                    html += `
                        <div class="ms-drop-item ${(already || outOfStock) ? 'disabled' : ''}"
                             data-id="${p.id}" data-name="${escapeHtml(p.name)}"
                             data-price="${sellPrice}" data-cost="${costPrice}" data-stock="${stock}">
                            <div class="ms-drop-info">
                                <strong>${escapeHtml(p.name)}</strong>
                                <span class="ms-drop-meta">
                                    <span><i class="fa-solid fa-cubes"></i> Stock: ${stock}</span>
                                    <span class="ms-drop-cost"><i class="fa-solid fa-tag"></i> Cost: ${fmtRs(costPrice)}</span>
                                    <span class="ms-drop-sell"><i class="fa-solid fa-sack-dollar"></i> Sell: ${fmtRs(sellPrice)}</span>
                                </span>
                            </div>
                            ${already ? '<span class="ms-drop-added"><i class="fa-solid fa-check"></i></span>' : (outOfStock ? '<span class="ms-drop-added" style="color:var(--danger);"><i class="fa-solid fa-ban"></i></span>' : '<i class="fa-solid fa-plus ms-drop-add"></i>')}
                        </div>
                    `;
                });
                dropdown.innerHTML = html;
                dropdown.classList.add('show');

                dropdown.querySelectorAll('.ms-drop-item:not(.disabled)').forEach(item => {
                    item.addEventListener('click', function () {
                        const id = parseInt(this.dataset.id);
                        const name = this.dataset.name;
                        const price = parseFloat(this.dataset.price);
                        const cost = parseFloat(this.dataset.cost);
                        const stock = parseInt(this.dataset.stock);
                        if (stock <= 0) return;
                        cart.push({
                            product_id: id, name: name, quantity: 1,
                            sale_price: price, cost_price: cost, stock: stock
                        });
                        searchInput.value = '';
                        dropdown.classList.remove('show');
                        dropdown.innerHTML = '';
                        renderSelected();
                    });
                });
            }

            // ✅ FIX: Sirf typing pe dropdown khulega, focus pe nahi
            searchInput.addEventListener('input', function () { showDropdown(this.value); });

            // ✅ FIX: Focus pe agar input khaali hai to dropdown nahi khulega
            searchInput.addEventListener('focus', function () {
                if (this.value.trim() !== '') {
                    showDropdown(this.value);
                }
            });

            document.addEventListener('click', function (e) {
                if (!dropdown.contains(e.target) && e.target !== searchInput) {
                    dropdown.classList.remove('show');
                    dropdown.innerHTML = '';
                }
            });

            cart = [];
            renderSelected();
        }, 80);
    }

    /* ---------- SALE DETAIL POPUP ---------- */
    function buildSaleDetail(data) {
        const isAdminView = data.is_admin_view;
        const uniqueProducts = new Set(data.items.map(i => i.product_name)).size;
        const isMulti = uniqueProducts > 1;
        const saleLabel = isMulti ? `Multi Sale #${data.id}` : `Sale #${data.id}`;

        let itemsRows = '';
        data.items.forEach(it => {
            let plHtml = '';
            if (isAdminView && it.profit_loss !== null) {
                const pl = it.profit_loss;
                if (pl > 0) plHtml = `<span class="pl-badge pl-profit">+${fmtRs(pl)}</span>`;
                else if (pl < 0) plHtml = `<span class="pl-badge pl-loss">-${fmtRs(Math.abs(pl))}</span>`;
                else plHtml = `<span class="pl-badge pl-zero">Rs 0</span>`;
            }
            itemsRows += `
                <tr>
                    <td><strong>${escapeHtml(it.product_name)}</strong></td>
                    <td class="text-right">${it.quantity}</td>
                    ${isAdminView ? `<td class="text-right">${fmtRs(it.cost_price)}</td>` : ''}
                    <td class="text-right">${fmtRs(it.sale_price)}</td>
                    <td class="text-right"><strong>${fmtRs(it.total)}</strong></td>
                    ${isAdminView ? `<td class="text-right">${plHtml}</td>` : ''}
                </tr>
            `;
        });

        const plNet = (data.total_profit || 0) - (data.total_loss || 0);
        const plColor = plNet > 0 ? 'var(--success)' : plNet < 0 ? 'var(--danger)' : 'var(--text-muted)';
        const plText = plNet > 0 ? '+ ' + fmtRs(plNet) + ' Profit' : plNet < 0 ? '- ' + fmtRs(Math.abs(plNet)) + ' Loss' : 'No Profit/Loss';

        return `
            <div class="sd-head">
                <div class="sd-icon"><i class="fa-solid fa-${isMulti ? 'layer-group' : 'receipt'}"></i></div>
                <div>
                    <h2 class="sd-title">${saleLabel}</h2>
                    <div class="sd-meta">
                        <span><i class="fa-solid fa-user"></i> ${escapeHtml(data.seller_name)}</span>
                        <span><i class="fa-solid fa-calendar"></i> ${escapeHtml(data.batch_date)}</span>
                    </div>
                </div>
            </div>

            <div class="sd-summary">
                <div class="sd-sum-card">
                    <div class="sd-sum-label">${isMulti ? 'Products' : 'Product'}</div>
                    <div class="sd-sum-val">${uniqueProducts}</div>
                </div>
                <div class="sd-sum-card">
                    <div class="sd-sum-label">Total Qty</div>
                    <div class="sd-sum-val">${data.total_quantity}</div>
                </div>
                <div class="sd-sum-card">
                    <div class="sd-sum-label">Total Amount</div>
                    <div class="sd-sum-val">${fmtRs(data.total_amount)}</div>
                </div>
                ${isAdminView ? `
                <div class="sd-sum-card" style="grid-column:1/-1;border-color:${plNet > 0 ? 'var(--success)' : plNet < 0 ? 'var(--danger)' : 'var(--border)'};">
                    <div class="sd-sum-label">${plNet >= 0 ? 'Profit' : 'Loss'}</div>
                    <div class="sd-sum-val" style="color:${plColor};font-size:18px;">${plText}</div>
                </div>
                ` : ''}
            </div>

            <div class="sd-items-wrap">
                <h3 class="sd-items-title"><i class="fa-solid fa-list"></i> ${isMulti ? 'Products' : 'Product'} (${data.items.length})</h3>
                <table class="sd-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            ${isAdminView ? `<th class="text-right">Cost</th>` : ''}
                            <th class="text-right">Sale Price</th>
                            <th class="text-right">Total</th>
                            ${isAdminView ? `<th class="text-right">P/L</th>` : ''}
                        </tr>
                    </thead>
                    <tbody>${itemsRows}</tbody>
                </table>
            </div>

            <div class="sd-footer">
                <i class="fa-regular fa-clock"></i> Sale recorded on ${escapeHtml(data.created_at)}
            </div>
        `;
    }

    function attachSaleDetailLogic() {
        setTimeout(function () {
            const submitBtn = document.getElementById('fpSubmit');
            const cancelBtn = document.getElementById('fpCancel');
            const overlay = document.getElementById('formPopupOverlay');
            submitBtn.style.display = 'none';
            cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Close';
            cancelBtn.className = 'modal-btn modal-confirm';
            const newCancel = cancelBtn.cloneNode(true);
            cancelBtn.replaceWith(newCancel);
            newCancel.addEventListener('click', function () {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }, 60);
    }

    window.showSaleDetailPopup = function (data) {
        const uniqueProducts = new Set(data.items.map(i => i.product_name)).size;
        const isMulti = uniqueProducts > 1;
        openFormPopup({
            title: isMulti ? 'Multi Sale Details' : 'Sale Details',
            subtitle: isMulti ? 'Multiple products in this sale' : 'Single product sale',
            icon: `<i class="fa-solid fa-${isMulti ? 'layer-group' : 'receipt'}"></i>`,
            iconType: 'info',
            action: '',
            submitText: '',
            body: buildSaleDetail(data)
        });
        attachSaleDetailLogic();
    };

    /* ---------- PRODUCT EDIT FORM ---------- */
    function buildProductEditForm(p) {
        return `
            <input type="hidden" name="edit_id" value="${p.id}">
            <label class="form-label">Product Name</label>
            <div class="input-group"><i class="fa-solid fa-box input-icon"></i>
                <input type="text" name="name" value="${escapeHtml(p.name)}" required>
            </div>
            <label class="form-label">Price per Item (Rs)</label>
            <div class="input-group"><i class="fa-solid fa-tag input-icon"></i>
                <input type="number" name="price" value="${p.price}" min="0" step="0.01" required>
            </div>
            <label class="form-label">Current Quantity (read-only)</label>
            <div class="input-group"><i class="fa-solid fa-cubes input-icon"></i>
                <input type="number" value="${p.quantity}" readonly style="background:var(--surface-alt);color:var(--text-muted);cursor:not-allowed;">
            </div>
            <div class="qty-actions">
                <div>
                    <label class="form-label"><i class="fa-solid fa-plus" style="color:var(--success);margin-right:4px;"></i> Add Quantity</label>
                    <div class="input-group"><i class="fa-solid fa-circle-plus input-icon"></i>
                        <input type="number" name="add_qty" placeholder="e.g. 20" min="0" step="1">
                    </div>
                </div>
                <div>
                    <label class="form-label"><i class="fa-solid fa-minus" style="color:var(--danger);margin-right:4px;"></i> Remove from Quantity</label>
                    <div class="input-group"><i class="fa-solid fa-circle-minus input-icon"></i>
                        <input type="number" name="remove_qty" placeholder="e.g. 5" min="0" step="1">
                    </div>
                </div>
            </div>
            <p class="qty-hint"><i class="fa-solid fa-circle-info"></i>
                New quantity = Current <strong>${p.quantity}</strong> + Add − Remove
            </p>`;
    }

    /* ---------- PRODUCT DETAIL ---------- */
    function buildProductDetail(p) {
        const isAdmin = !!p.is_admin;
        const lowBadge = p.low_stock ? '<span class="badge badge-danger" style="margin-left:8px;">Low Stock</span>' : '';
        const purchaseHistory = p.purchase_history || [];
        const salesHistory = p.sales_history || [];
        const summary = p.summary || {};

        let purchaseRows = '';
        if (purchaseHistory.length === 0) {
            purchaseRows = `<tr><td colspan="5" class="pd-empty">Koi purchase history nahi.</td></tr>`;
        } else {
            purchaseHistory.forEach(ph => {
                purchaseRows += `<tr>
                    <td class="muted">${escapeHtml(ph.date)}</td>
                    <td class="text-right">${ph.quantity}</td>
                    <td class="text-right">${fmtRs(ph.cost_price)}</td>
                    <td class="text-right">${fmtRs(ph.selling_price)}</td>
                    <td class="text-right" style="color:var(--success);">${fmtRs(ph.profit)}</td>
                </tr>`;
            });
        }

        let salesRows = '';
        if (salesHistory.length === 0) {
            salesRows = `<tr><td colspan="6" class="pd-empty">Koi sale history nahi.</td></tr>`;
        } else {
            salesHistory.forEach(sh => {
                const sellerDot = sh.seller_color ? `<span class="seller-dot" style="background:${sh.seller_color};width:8px;height:8px;margin-right:4px;"></span>` : '';
                const pl = parseFloat(sh.profit_loss) || 0;
                let plHtml = '<span class="pl-badge pl-zero" style="font-size:10px;padding:2px 6px;">No P/L</span>';
                if (pl > 0) plHtml = `<span class="pl-badge pl-profit" style="font-size:10px;padding:2px 6px;">+Rs ${pl.toFixed(2)}</span>`;
                else if (pl < 0) plHtml = `<span class="pl-badge pl-loss" style="font-size:10px;padding:2px 6px;">-Rs ${Math.abs(pl).toFixed(2)}</span>`;
                salesRows += `<tr>
                    <td class="muted">${escapeHtml(sh.date)}</td>
                    <td>${sellerDot}${escapeHtml(sh.seller)}</td>
                    <td class="text-right">${sh.quantity}</td>
                    <td class="text-right">${fmtRs(sh.price)}</td>
                    <td class="text-right"><strong>${fmtRs(sh.total)}</strong></td>
                    <td class="text-right">${plHtml}</td>
                </tr>`;
            });
        }

        return `
            <div class="product-detail-head">
                <div class="pd-icon"><i class="fa-solid fa-box"></i></div>
                <div>
                    <h2 class="pd-name">${escapeHtml(p.name)}${lowBadge}</h2>
                    <div class="pd-id">Product ID: #${p.id}</div>
                </div>
            </div>

            <div class="pd-grid">
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-cubes"></i> Current Stock</div><div class="pd-stat-value">${p.quantity}</div></div>
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-sack-dollar"></i> Stock Value</div><div class="pd-stat-value">${fmtRs(p.stock_value)}</div></div>
                ${isAdmin ? `
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-tag"></i> Purchase Price</div><div class="pd-stat-value">${fmtRs(p.cost_price)}</div></div>
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-money-bill-trend-up"></i> Selling Price</div><div class="pd-stat-value">${fmtRs(p.price)}</div></div>
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-chart-line"></i> Profit / Item</div><div class="pd-stat-value" style="color:var(--success);">${fmtRs(p.profit_per_item)}</div></div>
                <div class="pd-stat"><div class="pd-stat-label"><i class="fa-solid fa-percent"></i> Profit %</div><div class="pd-stat-value" style="color:var(--success);">${(parseFloat(p.profit_pct) || 0).toFixed(1)}%</div></div>
                ` : ''}
            </div>

            ${isAdmin ? `
            <div class="pd-summary-grid">
                <div class="pd-summary-card"><div class="pd-summary-label"><i class="fa-solid fa-cart-shopping"></i> Total Purchased</div><div class="pd-summary-value">${summary.total_purchased_qty || 0} items</div><div class="pd-summary-sub">${fmtRs(summary.total_purchase_cost || 0)}</div></div>
                <div class="pd-summary-card"><div class="pd-summary-label"><i class="fa-solid fa-receipt"></i> Total Sold</div><div class="pd-summary-value">${summary.total_sold_qty || 0} items</div><div class="pd-summary-sub">${fmtRs(summary.total_sold_revenue || 0)}</div></div>
                <div class="pd-summary-card highlight"><div class="pd-summary-label"><i class="fa-solid fa-coins"></i> Total Profit</div><div class="pd-summary-value" style="color:var(--success);">${fmtRs(summary.total_sold_profit || 0)}</div><div class="pd-summary-sub">from sales</div></div>
                <div class="pd-summary-card highlight-loss"><div class="pd-summary-label"><i class="fa-solid fa-arrow-trend-down"></i> Total Loss</div><div class="pd-summary-value" style="color:var(--danger);">${fmtRs(summary.total_sold_loss || 0)}</div><div class="pd-summary-sub">from sales</div></div>
            </div>

            <div class="pd-history-section">
                <h3 class="pd-history-title"><i class="fa-solid fa-cart-shopping"></i> Purchase History <span class="pd-history-count">${purchaseHistory.length}</span></h3>
                <div class="pd-table-wrap"><table class="pd-table"><thead><tr><th>Date</th><th class="text-right">Qty</th><th class="text-right">Purchase</th><th class="text-right">Selling</th><th class="text-right">Profit</th></tr></thead><tbody>${purchaseRows}</tbody></table></div>
            </div>

            <div class="pd-history-section">
                <h3 class="pd-history-title"><i class="fa-solid fa-receipt"></i> Sales History <span class="pd-history-count">${salesHistory.length}</span></h3>
                <div class="pd-table-wrap"><table class="pd-table"><thead><tr><th>Date</th><th>Sold By</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th><th class="text-right">P/L</th></tr></thead><tbody>${salesRows}</tbody></table></div>
            </div>
            ` : ''}

            <div class="pd-footer"><i class="fa-regular fa-clock"></i> Added on ${escapeHtml(p.created_at)}</div>
        `;
    }

    function attachProductDetailLogic() {
        setTimeout(function () {
            const submitBtn = document.getElementById('fpSubmit');
            const cancelBtn = document.getElementById('fpCancel');
            const overlay = document.getElementById('formPopupOverlay');
            submitBtn.style.display = 'none';
            cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Close';
            cancelBtn.className = 'modal-btn modal-confirm';
            const newCancel = cancelBtn.cloneNode(true);
            cancelBtn.replaceWith(newCancel);
            newCancel.addEventListener('click', function () {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }, 60);
    }

    /* ---------- PROFILES POPUP ---------- */
    function buildProfilesForm(me, members) {
        const isAdmin = me.role === 'admin' && !me.switched;
        const isSwitched = !!me.switched;
        const maskedUser = maskText(me.username);
        const maskedEmail = maskText(me.email);
        const myColor = isValidHex(me.color) ? me.color : '#4f46e5';

        let membersListHtml = '';
        if (isAdmin) {
            if (!members || members.length === 0) {
                membersListHtml = `<p class="empty" style="padding:14px 0;font-size:12px;">Koi team member nahi hai.</p>`;
            } else {
                membersListHtml = '<ul class="member-list">';
                members.forEach(m => {
                    const roleTag = m.role === 'admin'
                        ? '<span class="badge badge-success" style="font-size:10px;">Admin</span>'
                        : '<span class="badge" style="background:var(--info-bg);color:var(--info);font-size:10px;">Member</span>';
                    const isMe = m.id == me.id || m.username === me.username;
                    const mColor = isValidHex(m.color) ? m.color : '#4f46e5';
                    let actions = '';
                    if (m.role === 'member' && !isMe) {
                        actions = `
                            <a href="member-switch.php?id=${m.id}" class="member-action switch" title="Switch"
                               data-confirm="Switch to ${escapeHtml(m.username)}'s account?"
                               data-confirm-title="Switch Account?"
                               data-confirm-btn="Yes, Switch"
                               data-cancel-btn="Cancel"
                               data-type="info"
                               data-icon='<i class="fa-solid fa-right-left"></i>'>
                                <i class="fa-solid fa-right-left"></i>
                            </a>
                            <button type="button" class="member-action" data-member-edit='${JSON.stringify({id:m.id, username:m.username, email:m.email, color:mColor}).replace(/'/g, "&#39;")}' title="Edit"><i class="fa-solid fa-pen"></i></button>
                            <a href="member-delete.php?id=${m.id}" class="member-action danger" title="Delete"
                               data-confirm="This team member will be permanently deleted."
                               data-confirm-title="Delete ${escapeHtml(m.username)}?"
                               data-confirm-btn="Yes, Delete"
                               data-cancel-btn="Cancel"
                               data-danger="true"
                               data-type="danger"
                               data-icon='<i class="fa-solid fa-user-xmark"></i>'>
                                <i class="fa-solid fa-trash"></i>
                            </a>`;
                    }
                    membersListHtml += `
                        <li>
                            <div class="member-info">
                                <div class="member-avatar" style="background:${mColor}22;color:${mColor};border:2px solid ${mColor};"><i class="fa-solid fa-user"></i></div>
                                <div style="min-width:0;flex:1;">
                                    <div class="member-name">${escapeHtml(m.username)} ${isMe ? '<span class="muted" style="font-size:11px;">(you)</span>' : ''}</div>
                                    <div class="member-email">${escapeHtml(m.email)}</div>
                                </div>
                                ${roleTag}
                            </div>
                            <div class="member-actions">${actions}</div>
                        </li>`;
                });
                membersListHtml += '</ul>';
            }
        }

        let noteHtml = '';
        if (isSwitched) {
            noteHtml = `<p class="qty-hint" style="margin-top:16px;"><i class="fa-solid fa-right-left"></i>Aap <strong>switched view</strong> mein hain. Admin powers ke liye "Back to Admin" dabayein.</p>`;
        } else if (!isAdmin) {
            noteHtml = `<p class="qty-hint" style="margin-top:16px;"><i class="fa-solid fa-lock"></i>Team member apni profile edit nahi kar sakta. Admin se rabta karein.</p>`;
        }

        const canEdit = isAdmin;

        return `
            <div id="profileView">
                <div class="profile-info-row"><span class="profile-info-label">Username</span><span class="profile-info-value">${escapeHtml(maskedUser)}</span></div>
                <div class="profile-info-row"><span class="profile-info-label">Email</span><span class="profile-info-value">${escapeHtml(maskedEmail)}</span></div>
                <div class="profile-info-row"><span class="profile-info-label">Password</span><span class="profile-info-value">••••••••</span></div>
                <div class="profile-info-row"><span class="profile-info-label">Color</span><span class="profile-info-value"><span class="seller-dot" style="background:${myColor};width:14px;height:14px;display:inline-block;border-radius:50%;vertical-align:middle;margin-right:6px;"></span><span style="font-family:monospace;">${myColor}</span></span></div>
                ${noteHtml}
            </div>
            ${canEdit ? `
            <div id="profileEdit" style="display:none;">
                <input type="hidden" name="update_profile" value="1">
                <label class="form-label">Username</label>
                <div class="input-group"><i class="fa-solid fa-user input-icon"></i><input type="text" name="username" value="${escapeHtml(me.username)}" required></div>
                <label class="form-label">Email</label>
                <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i><input type="email" name="email" value="${escapeHtml(me.email)}" required></div>
                <label class="form-label">New Password <span class="muted" style="font-weight:400;font-size:12px;">(khali chhorein agar change nahi karna)</span></label>
                <div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="password" id="profilePassword" placeholder="New password" minlength="6"><button type="button" class="eye-btn" data-pass-toggle="profilePassword" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div>
                <label class="form-label">Confirm New Password</label>
                <div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="confirm_password" id="profileConfirm" placeholder="Confirm password" minlength="6"><button type="button" class="eye-btn" data-pass-toggle="profileConfirm" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div>
                ${buildColorPicker(myColor, 'color')}
            </div>
            <div class="members-section">
                <div class="members-header">
                    <h3><i class="fa-solid fa-users"></i> Team Members (${members.length})</h3>
                    <button type="button" class="btn-sm-primary" id="addMemberBtn"><i class="fa-solid fa-plus"></i> Add Member</button>
                </div>
                ${membersListHtml}
            </div>
            ` : ''}
        `;
    }

    function attachProfilesLogic(me, members) {
        setTimeout(function () {
            const view = document.getElementById('profileView');
            const edit = document.getElementById('profileEdit');
            const submitBtn = document.getElementById('fpSubmit');
            const cancelBtn = document.getElementById('fpCancel');
            const overlay = document.getElementById('formPopupOverlay');
            const body = document.getElementById('fpBody');
            const form = document.getElementById('fpForm');
            const isAdmin = me.role === 'admin' && !me.switched;

            if (!isAdmin) {
                submitBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                return;
            }

            submitBtn.style.display = 'none';
            cancelBtn.innerHTML = '<i class="fa-solid fa-pen"></i> Edit Profile';
            cancelBtn.className = 'modal-btn modal-confirm';

            const newCancel = cancelBtn.cloneNode(true);
            cancelBtn.replaceWith(newCancel);

            newCancel.addEventListener('click', function () {
                if (view.style.display !== 'none') {
                    view.style.display = 'none';
                    edit.style.display = '';
                    submitBtn.style.display = '';
                    newCancel.innerHTML = '<i class="fa-solid fa-xmark"></i> Back';
                    newCancel.className = 'modal-btn modal-cancel';
                    attachColorPickerLogic(edit);
                } else {
                    view.style.display = '';
                    edit.style.display = 'none';
                    submitBtn.style.display = 'none';
                    newCancel.innerHTML = '<i class="fa-solid fa-pen"></i> Edit Profile';
                    newCancel.className = 'modal-btn modal-confirm';
                }
            });

            edit.querySelectorAll('[data-pass-toggle]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const inp = document.getElementById(this.dataset.passToggle);
                    const icon = this.querySelector('i');
                    if (inp.type === 'password') { inp.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
                    else { inp.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
                });
            });

            const addBtn = document.getElementById('addMemberBtn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    form.action = 'member-add.php';
                    submitBtn.style.display = '';
                    submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Create Member';
                    newCancel.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Back';
                    newCancel.className = 'modal-btn modal-cancel';
                    body.innerHTML = `
                        <label class="form-label">Username</label>
                        <div class="input-group"><i class="fa-solid fa-user input-icon"></i><input type="text" name="username" placeholder="Member username" required></div>
                        <label class="form-label">Email</label>
                        <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i><input type="email" name="email" placeholder="Member email" required></div>
                        <label class="form-label">Password</label>
                        <div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="password" id="memberPassword" placeholder="Min 6 chars" minlength="6" required><button type="button" class="eye-btn" data-pass-toggle="memberPassword" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div>
                        ${buildColorPicker('#16a34a', 'color')}
                        <p class="qty-hint"><i class="fa-solid fa-circle-info"></i>Team member product add/edit/delete nahi kar sakta. Color sales table mein row highlight ke liye use hoga.</p>
                    `;
                    attachColorPickerLogic(body);
                    body.querySelectorAll('[data-pass-toggle]').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const inp = document.getElementById(this.dataset.passToggle);
                            const icon = this.querySelector('i');
                            if (inp.type === 'password') { inp.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
                            else { inp.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
                        });
                    });
                    const c2 = newCancel.cloneNode(true);
                    newCancel.replaceWith(c2);
                    c2.addEventListener('click', function () {
                        overlay.classList.remove('active');
                        const trigger = document.querySelector('[data-popup-form="profiles"]');
                        if (trigger) trigger.click();
                    });
                });
            }

            body.querySelectorAll('[data-member-edit]').forEach(btn => {
                btn.addEventListener('click', function () {
                    let data;
                    try { data = JSON.parse(this.dataset.memberEdit); }
                    catch (e) { data = JSON.parse(this.dataset.memberEdit.replace(/&#39;/g, "'")); }
                    form.action = 'member-edit.php';
                    submitBtn.style.display = '';
                    submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Update Member';
                    newCancel.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Back';
                    newCancel.className = 'modal-btn modal-cancel';
                    body.innerHTML = `
                        <input type="hidden" name="id" value="${data.id}">
                        <label class="form-label">Username</label>
                        <div class="input-group"><i class="fa-solid fa-user input-icon"></i><input type="text" name="username" value="${escapeHtml(data.username)}" required></div>
                        <label class="form-label">Email</label>
                        <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i><input type="email" name="email" value="${escapeHtml(data.email)}" required></div>
                        <label class="form-label">New Password <span class="muted" style="font-weight:400;font-size:12px;">(khali chhorein agar change nahi karna)</span></label>
                        <div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="password" id="memberEditPassword" placeholder="New password" minlength="6"><button type="button" class="eye-btn" data-pass-toggle="memberEditPassword" tabindex="-1"><i class="fa-solid fa-eye"></i></button></div>
                        ${buildColorPicker(data.color || '#16a34a', 'color')}
                    `;
                    attachColorPickerLogic(body);
                    body.querySelectorAll('[data-pass-toggle]').forEach(b => {
                        b.addEventListener('click', function () {
                            const inp = document.getElementById(this.dataset.passToggle);
                            const icon = this.querySelector('i');
                            if (inp.type === 'password') { inp.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
                            else { inp.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
                        });
                    });
                    const c2 = newCancel.cloneNode(true);
                    newCancel.replaceWith(c2);
                    c2.addEventListener('click', function () {
                        overlay.classList.remove('active');
                        const trigger = document.querySelector('[data-popup-form="profiles"]');
                        if (trigger) trigger.click();
                    });
                });
            });
        }, 60);
    }

    /* ---------- ATTACH HANDLERS ---------- */
    function attachHandlers() {
        document.querySelectorAll('a[data-confirm]').forEach(function (link) {
            if (link.dataset.popupBound === '1') return;
            link.dataset.popupBound = '1';
            link.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                pendingElement = this; pendingType = 'link';
                openModal({
                    title: this.dataset.confirmTitle || 'Are you sure?',
                    message: this.dataset.confirm || '',
                    confirmText: this.dataset.confirmBtn || 'Confirm',
                    cancelText: this.dataset.cancelBtn || 'Cancel',
                    danger: this.dataset.danger === 'true',
                    type: this.dataset.type || (this.dataset.danger === 'true' ? 'danger' : 'warning'),
                    icon: this.dataset.icon || null
                });
                return false;
            });
        });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            if (form.dataset.popupBound === '1') return;
            form.dataset.popupBound = '1';
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') { form.dataset.confirmed = '0'; return; }
                e.preventDefault(); e.stopPropagation();
                pendingElement = this; pendingType = 'form';
                openModal({
                    title: this.dataset.confirmTitle || 'Are you sure?',
                    message: this.dataset.confirm || '',
                    confirmText: this.dataset.confirmBtn || 'Confirm',
                    cancelText: this.dataset.cancelBtn || 'Cancel',
                    danger: this.dataset.danger === 'true',
                    type: this.dataset.type || (this.dataset.danger === 'true' ? 'danger' : 'warning'),
                    icon: this.dataset.icon || null
                });
                return false;
            });
        });

        document.querySelectorAll('[data-popup-form]').forEach(function (el) {
            if (el.dataset.formBound === '1') return;
            el.dataset.formBound = '1';
            el.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                const type = this.dataset.popupForm;

                if (type === 'multi-sale') {
                    const products = JSON.parse(this.dataset.products || '[]');
                    const isAdminView = this.dataset.isAdmin === '1';
                    openFormPopup({
                        title: 'New Sale',
                        subtitle: 'Ek ya zyada products select karein',
                        icon: '<i class="fa-solid fa-cart-plus"></i>',
                        iconType: 'success',
                        action: this.dataset.action || 'sales.php',
                        submitText: 'Save Sale',
                        body: buildMultiSaleForm(products, isAdminView)
                    });
                    attachMultiSaleLogic(products, isAdminView);
                } else if (type === 'product-edit') {
                    const p = JSON.parse(this.dataset.product || '{}');
                    openFormPopup({
                        title: 'Edit Product', subtitle: 'Product details update karein',
                        icon: '<i class="fa-solid fa-pen"></i>', iconType: 'warning',
                        action: 'products.php', submitText: 'Update Product',
                        body: buildProductEditForm(p)
                    });
                } else if (type === 'product-detail') {
                    const p = JSON.parse(this.dataset.product || '{}');
                    openFormPopup({
                        title: 'Product Details', subtitle: 'Full product information',
                        icon: '<i class="fa-solid fa-box"></i>', iconType: 'info',
                        action: '', submitText: '',
                        body: buildProductDetail(p)
                    });
                    attachProductDetailLogic();
                } else if (type === 'profiles') {
                    const me = JSON.parse(this.dataset.user || '{}');
                    const members = JSON.parse(this.dataset.members || '[]');
                    openFormPopup({
                        title: 'My Profile',
                        subtitle: me.switched ? 'Switched view' : (me.role === 'admin' ? 'Profile & team management' : 'Your account details'),
                        icon: '<i class="fa-solid fa-user"></i>', iconType: 'info',
                        action: 'profile-update.php', submitText: 'Save Changes',
                        body: buildProfilesForm(me, members)
                    });
                    attachProfilesLogic(me, members);
                }
            });
        });
    }

    /* ---------- ALERTS ---------- */
    function initAutoDismissAlerts() {
        const alerts = document.querySelectorAll('.alert');
        if (!alerts.length) return;
        alerts.forEach(function (alert) {
            if (alert.dataset.autoDismiss === '1') return;
            alert.dataset.autoDismiss = '1';
            if (!alert.querySelector('.alert-close')) {
                const btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'alert-close';
                btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                btn.addEventListener('click', function () { dismissAlert(alert); });
                alert.appendChild(btn);
            }
            if (!alert.querySelector('.alert-progress')) {
                const bar = document.createElement('div');
                bar.className = 'alert-progress';
                bar.innerHTML = '<div class="alert-progress-fill"></div>';
                alert.appendChild(bar);
            }
            setTimeout(function () { dismissAlert(alert); }, 3000);
        });
    }

    function dismissAlert(alert) {
        if (alert.classList.contains('alert-hide')) return;
        alert.classList.add('alert-hide');
        setTimeout(function () { alert.remove(); }, 300);
    }

    function initFormTransitions() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.hasAttribute('data-confirm')) return;
            form.addEventListener('submit', function () {
                if (form.dataset.noFade === '1') return;
                setTimeout(function () {
                    const m = getMainContent();
                    if (m) { m.classList.remove('mc-active'); m.classList.add('mc-exit'); }
                }, 10);
            });
        });
    }

    function init() {
        initMobileMenu();
        initPageTransitions();
        injectModal();
        injectFormModal();
        attachHandlers();
        initAutoDismissAlerts();
        initFormTransitions();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            const m = getMainContent();
            if (m) { m.classList.remove('mc-enter', 'mc-exit'); m.classList.add('mc-active'); }
        }
    });

    window.initPopups = attachHandlers;
    window.initAlerts = initAutoDismissAlerts;
    window.openFormPopup = openFormPopup;
})();