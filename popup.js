/* ============================================================
   Custom Popup + Alerts + Transitions + Form Popups + Profiles + Color
   ============================================================ */

(function () {
    'use strict';

    let pendingElement = null;
    let pendingType = null;

    const COLOR_PRESETS = [
        '#4f46e5', // indigo
        '#2563eb', // blue
        '#0891b2', // cyan
        '#16a34a', // green
        '#65a30d', // lime
        '#ca8a04', // yellow
        '#ea580c', // orange
        '#dc2626', // red
        '#db2777', // pink
        '#9333ea', // purple
        '#475569', // slate
        '#0f172a', // near black
    ];

    function getMainContent() { return document.querySelector('.main-content'); }

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
            if (first) first.focus();
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

    /* ---------- COLOR PICKER BUILDER ---------- */
    function buildColorPicker(currentColor, fieldName = 'color') {
        currentColor = isValidHex(currentColor) ? currentColor : '#4f46e5';
        let swatches = '';
        COLOR_PRESETS.forEach(c => {
            const selected = c.toLowerCase() === currentColor.toLowerCase() ? 'selected' : '';
            swatches += `<button type="button" class="color-swatch ${selected}" data-color="${c}" style="background:${c};" title="${c}"></button>`;
        });
        return `
            <label class="form-label">Profile Color <span class="muted" style="font-weight:400;font-size:12px;">(sales row is color se highlight hogi)</span></label>
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
        const hex    = picker.querySelector('[data-color-hex]');
        const swatches = picker.querySelectorAll('.color-swatch');

        function setColor(c) {
            if (!isValidHex(c)) return;
            native.value = c;
            hex.value = c;
            swatches.forEach(s => s.classList.toggle('selected', s.dataset.color.toLowerCase() === c.toLowerCase()));
        }

        swatches.forEach(s => {
            s.addEventListener('click', function () { setColor(this.dataset.color); });
        });

        native.addEventListener('input', function () { setColor(this.value); });

        hex.addEventListener('input', function () {
            const v = this.value.trim();
            if (isValidHex(v)) {
                native.value = v;
                swatches.forEach(s => s.classList.toggle('selected', s.dataset.color.toLowerCase() === v.toLowerCase()));
            }
        });
    }

    /* ---------- SALE FORM ---------- */
    function buildSaleForm(products) {
        let options = '<option value="">-- Choose product --</option>';
        products.forEach(p => {
            options += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.quantity}">
                ${escapeHtml(p.name)} (Stock: ${p.quantity} | Rs ${p.price})
            </option>`;
        });
        return `
            <label class="form-label">Select Product</label>
            <div class="input-group">
                <i class="fa-solid fa-box input-icon"></i>
                <select name="product_id" id="popupSaleProduct" required>${options}</select>
            </div>
            <label class="form-label">Quantity Sold</label>
            <div class="input-group">
                <i class="fa-solid fa-hashtag input-icon"></i>
                <input type="number" name="quantity" id="popupSaleQty" placeholder="e.g. 5" min="1" step="1" required>
            </div>
            <div class="sale-summary">
                <div><span>Price / item</span><strong id="popupSumPrice">Rs 0.00</strong></div>
                <div><span>Total</span><strong id="popupSumTotal">Rs 0.00</strong></div>
            </div>`;
    }

    function attachSaleFormLogic() {
        setTimeout(function () {
            const sel = document.getElementById('popupSaleProduct');
            const qty = document.getElementById('popupSaleQty');
            if (!sel || !qty) return;
            function update() {
                const opt = sel.options[sel.selectedIndex];
                const price = parseFloat(opt?.dataset?.price || 0);
                const q = parseInt(qty.value || 0);
                document.getElementById('popupSumPrice').textContent = 'Rs ' + price.toFixed(2);
                document.getElementById('popupSumTotal').textContent = 'Rs ' + (price * q).toFixed(2);
            }
            sel.addEventListener('change', update);
            qty.addEventListener('input', update);
            update();
        }, 60);
    }

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
                            <a href="member-switch.php?id=${m.id}" class="member-action switch" title="Switch to this account"
                               data-confirm="Switch to ${escapeHtml(m.username)}'s account? Aap member view mein chale jayenge."
                               data-confirm-title="Switch Account?"
                               data-confirm-btn="Yes, Switch"
                               data-cancel-btn="Cancel"
                               data-type="info"
                               data-icon='<i class="fa-solid fa-right-left"></i>'>
                                <i class="fa-solid fa-right-left"></i>
                            </a>
                            <button type="button" class="member-action" data-member-edit='${JSON.stringify({id:m.id, username:m.username, email:m.email, color:mColor}).replace(/'/g, "&#39;")}' title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
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
            noteHtml = `<p class="qty-hint" style="margin-top:16px;">
                <i class="fa-solid fa-right-left"></i>
                Aap <strong>switched view</strong> mein hain (as ${escapeHtml(me.username)}). Admin powers ke liye "Back to Admin" dabayein.
            </p>`;
        } else if (!isAdmin) {
            noteHtml = `<p class="qty-hint" style="margin-top:16px;">
                <i class="fa-solid fa-lock"></i>
                Team member apni profile edit nahi kar sakta. Admin se rabta karein.
            </p>`;
        }

        const canEdit = isAdmin;

        return `
            <div id="profileView">
                <div class="profile-info-row">
                    <span class="profile-info-label">Username</span>
                    <span class="profile-info-value">${escapeHtml(maskedUser)}</span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Email</span>
                    <span class="profile-info-value">${escapeHtml(maskedEmail)}</span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Password</span>
                    <span class="profile-info-value">••••••••</span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Color</span>
                    <span class="profile-info-value">
                        <span class="seller-dot" style="background:${myColor};width:14px;height:14px;display:inline-block;border-radius:50%;vertical-align:middle;margin-right:6px;"></span>
                        <span style="font-family:monospace;">${myColor}</span>
                    </span>
                </div>
                ${noteHtml}
            </div>

            ${canEdit ? `
            <div id="profileEdit" style="display:none;">
                <input type="hidden" name="update_profile" value="1">
                <label class="form-label">Username</label>
                <div class="input-group"><i class="fa-solid fa-user input-icon"></i>
                    <input type="text" name="username" value="${escapeHtml(me.username)}" required>
                </div>
                <label class="form-label">Email</label>
                <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" value="${escapeHtml(me.email)}" required>
                </div>
                <label class="form-label">New Password <span class="muted" style="font-weight:400;font-size:12px;">(khali chhorein agar change nahi karna)</span></label>
                <div class="input-group"><i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password" id="profilePassword" placeholder="New password" minlength="6">
                    <button type="button" class="eye-btn" data-pass-toggle="profilePassword" tabindex="-1">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <label class="form-label">Confirm New Password</label>
                <div class="input-group"><i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="confirm_password" id="profileConfirm" placeholder="Confirm password" minlength="6">
                    <button type="button" class="eye-btn" data-pass-toggle="profileConfirm" tabindex="-1">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                ${buildColorPicker(myColor, 'color')}
            </div>

            <div class="members-section">
                <div class="members-header">
                    <h3><i class="fa-solid fa-users"></i> Team Members (${members.length})</h3>
                    <button type="button" class="btn-sm-primary" id="addMemberBtn">
                        <i class="fa-solid fa-plus"></i> Add Member
                    </button>
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
                        <div class="input-group"><i class="fa-solid fa-user input-icon"></i>
                            <input type="text" name="username" placeholder="Member username" required>
                        </div>
                        <label class="form-label">Email</label>
                        <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="Member email" required>
                        </div>
                        <label class="form-label">Password</label>
                        <div class="input-group"><i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="memberPassword" placeholder="Min 6 chars" minlength="6" required>
                            <button type="button" class="eye-btn" data-pass-toggle="memberPassword" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        ${buildColorPicker('#16a34a', 'color')}
                        <p class="qty-hint"><i class="fa-solid fa-circle-info"></i>
                            Team member <strong>product add/edit/delete</strong> nahi kar sakta. Sirf view + sales record + apni profile edit nahi kar sakta. Color sales table mein row highlight ke liye use hoga.
                        </p>
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
                        <div class="input-group"><i class="fa-solid fa-user input-icon"></i>
                            <input type="text" name="username" value="${escapeHtml(data.username)}" required>
                        </div>
                        <label class="form-label">Email</label>
                        <div class="input-group"><i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" name="email" value="${escapeHtml(data.email)}" required>
                        </div>
                        <label class="form-label">New Password <span class="muted" style="font-weight:400;font-size:12px;">(khali chhorein agar change nahi karna)</span></label>
                        <div class="input-group"><i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="memberEditPassword" placeholder="New password" minlength="6">
                            <button type="button" class="eye-btn" data-pass-toggle="memberEditPassword" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
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

                if (type === 'product') {
                    openFormPopup({
                        title: 'Add Product', subtitle: 'Naya product inventory mein add karein',
                        icon: '<i class="fa-solid fa-box"></i>', iconType: 'info',
                        action: 'products.php', submitText: 'Save Product',
                        body: `
                            <label class="form-label">Product Name</label>
                            <div class="input-group"><i class="fa-solid fa-box input-icon"></i>
                                <input type="text" name="name" placeholder="e.g. Sugar 1kg" required>
                            </div>
                            <label class="form-label">Quantity</label>
                            <div class="input-group"><i class="fa-solid fa-cubes input-icon"></i>
                                <input type="number" name="quantity" placeholder="e.g. 100" min="0" step="1" required>
                            </div>
                            <label class="form-label">Price per Item (Rs)</label>
                            <div class="input-group"><i class="fa-solid fa-tag input-icon"></i>
                                <input type="number" name="price" placeholder="e.g. 120.00" min="0" step="0.01" required>
                            </div>`
                    });
                } else if (type === 'sale') {
                    const products = JSON.parse(this.dataset.products || '[]');
                    openFormPopup({
                        title: 'Record New Sale', subtitle: 'Sale hone par quantity update karein',
                        icon: '<i class="fa-solid fa-cart-plus"></i>', iconType: 'success',
                        action: this.dataset.action || 'sales.php', submitText: 'Save Sale',
                        body: buildSaleForm(products)
                    });
                    attachSaleFormLogic();
                } else if (type === 'product-edit') {
                    const p = JSON.parse(this.dataset.product || '{}');
                    openFormPopup({
                        title: 'Edit Product', subtitle: 'Product details update karein',
                        icon: '<i class="fa-solid fa-pen"></i>', iconType: 'warning',
                        action: 'products.php', submitText: 'Update Product',
                        body: buildProductEditForm(p)
                    });
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