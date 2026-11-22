const stateLifetime = 10 * 60 * 1000;
const paidDuration = 10_000;
const displayChannel = ({ shopId, terminalId }) => 'shop.' + shopId + (terminalId ? '.terminal.' + terminalId : '') + '.pos-display';
const pollInterval = 2500;
const idleState = () => ({ status: 'idle', items: [], subtotal: 0, discount_amount: 0, grand_total: 0 });
const formatRp = value => Number(value || 0).toLocaleString('id-ID');
const uuidV4 = /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i;

function readStorage(key) {
    try {
        return JSON.parse(localStorage.getItem(key)) ?? null;
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Embedded browsers may disable storage.
    }
}

function transport(config, onLocal) {
    const { shopId, terminalId } = config;
    const scope = terminalId ? shopId + '_' + terminalId : shopId;
    const stateKey = 'oqari_pos_cfd_state_' + scope;
    const pendingKey = 'oqari_pos_kiosk_pending_' + scope;
    const messageKey = 'oqari_pos_cfd_message_' + scope;
    let channel;
    try {
        if (typeof BroadcastChannel === 'function') channel = new BroadcastChannel('oqari_pos_cfd_' + scope);
    } catch {
        // Storage events provide the local fallback.
    }

    if (channel) channel.onmessage = event => onLocal(event.data);
    const onStorage = event => {
        if (!channel && event.key === messageKey && event.newValue) {
            try {
                onLocal(JSON.parse(event.newValue));
            } catch {
                // Ignore messages from an older or invalid client.
            }
        }
    };
    window.addEventListener('storage', onStorage);

    return {
        send(type, payload = null) {
            const message = { type, payload };
            if (channel) channel.postMessage(message);
            else writeStorage(messageKey, { ...message, id: window.newRequestId() });
        },
        readState: () => readStorage(stateKey),
        writeState: state => writeStorage(stateKey, state),
        readPending() {
            const entries = readStorage(pendingKey);
            return Array.isArray(entries)
                ? entries.filter(entry => entry?.action && Date.now() - entry.createdAt < stateLifetime)
                : [];
        },
        writePending: entries => writeStorage(pendingKey, entries),
        close() {
            channel?.close();
            window.removeEventListener('storage', onStorage);
        },
    };
}

async function request(url, payload) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        signal: globalThis.AbortSignal?.timeout?.(8000),
        method: payload === undefined ? 'GET' : 'POST',
        headers: {
            Accept: 'application/json',
            ...(payload !== undefined && {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            }),
        },
        ...(payload !== undefined && { body: JSON.stringify(payload) }),
    });
    if (!response.ok) {
        const error = new Error((await response.json().catch(() => ({}))).message || 'Sinkronisasi gagal.');
        error.status = response.status;
        throw error;
    }

    return response.json();
}

function freshState(state) {
    return state && Date.now() - Date.parse(state.updated_at) < stateLifetime
        && (state.status !== 'paid' || state.paid_until > Date.now());
}

function displayItem(item) {
    const modifiers = (item.modifiers ?? []).filter(modifier => modifier && typeof modifier === 'object');
    return {
        id: Number(item.id),
        line_key: item.line_key,
        name: item.name,
        variant_id: item.variant_id ? Number(item.variant_id) : null,
        variant: item.variant,
        modifier_ids: modifiers.map(modifier => Number(modifier.id)),
        modifiers: modifiers.map(modifier => modifier.name),
        notes: item.notes || null,
        qty: Number(item.qty),
        price: Number(item.price),
        subtotal: Number(item.subtotal),
        serving_type: item.serving_type,
    };
}

function modifierBounds(group) {
    return {
        min: Math.max(group.is_required ? 1 : 0, Number(group.min_selections || 0)),
        max: group.selection_type === 'SINGLE' ? 1 : Number(group.max_selections || group.modifiers.length),
    };
}

function cartItem(selection, products, salesModes) {
    const product = products.get(Number(selection?.id));
    const qty = Number(selection?.qty);
    if (!product || product.is_sold_out || !uuidV4.test(selection.line_key) ||
        !Number.isInteger(qty) || qty < 1 || qty > 100 || !salesModes.includes(selection.serving_type)) return null;

    const variant = product.variants.find(choice => choice.id === Number(selection.variant_id));
    if (product.variants.length && !variant) return null;

    const ids = new Set((selection.modifiers ?? []).map(Number));
    const modifiers = [];
    for (const group of product.modifier_groups) {
        const selected = group.modifiers.filter(modifier => ids.has(modifier.id));
        const { min, max } = modifierBounds(group);
        if (selected.length < min || selected.length > max) return null;
        modifiers.push(...selected);
    }
    if (modifiers.length !== ids.size) return null;

    const price = Number(product.effective_price) + Number(variant?.price_adjustment || 0)
        + modifiers.reduce((total, modifier) => total + Number(modifier.price_adjustment), 0);
    const notes = String(selection.notes ?? '').trim().slice(0, 255);
    return {
        id: product.id, line_key: selection.line_key, name: product.name, image: product.image_url,
        variant_id: variant?.id ?? null, variant: variant?.name ?? null, modifiers,
        notes, showNotes: !!notes, qty, price, subtotal: price * qty, serving_type: selection.serving_type,
        regular_price: Number(product.price), has_discount: product.has_active_discount,
        discount_percentage: product.discount_percentage,
    };
}

function applyCartAction(cart, action, makeItem) {
    const index = cart.findIndex(item => item.line_key === action.line_key);
    switch (action.action) {
        case 'add_item': {
            const item = makeItem(action.item);
            return item && cart.length < 50 && !cart.some(row => row.line_key === item.line_key) ? [...cart, item] : cart;
        }
        case 'update_qty': {
            const delta = Number(action.delta);
            if (index < 0 || ![-1, 1].includes(delta)) return cart;
            const row = cart[index];
            const qty = Number(row.qty) + delta;
            if (qty > 100) return cart;
            if (qty < 1) return cart.filter((_, position) => position !== index);
            return cart.map((item, position) => position === index ? { ...row, qty, subtotal: row.price * qty } : item);
        }
        case 'remove_item':
            return index < 0 ? cart : cart.filter((_, position) => position !== index);
        case 'clear_cart':
            return [];
        case 'sync_cart': {
            if (cart.length || !Array.isArray(action.items) || action.items.length > 50) return cart;
            const items = action.items.map(makeItem);
            return items.every(Boolean) && new Set(items.map(item => item.line_key)).size === items.length ? items : cart;
        }
        default:
            return cart;
    }
}

function screens(config, kiosk) {
    let bus;
    let heartbeat;
    let clock;
    let paidTimer;
    let echo;
    let latest;
    let localSeenAt = 0;
    let pollBusy = false;
    let sendBusy = false;
    let lastCloudAt = 0;
    let pending = [];
    const channelName = displayChannel(config);
    const products = new Map((config.products ?? []).map(product => [product.id, product]));
    const salesModes = (config.salesModes ?? []).filter(mode => ['DINE_IN', 'TAKEAWAY'].includes(mode));
    const tapTimes = new Map();
    let onFullscreenChange;

    return {
        state: idleState(),
        products: config.products ?? [],
        salesModes,
        search: '',
        activeCategory: null,
        showMobileCart: false,
        modifierModal: { show: false },
        actionError: '',
        syncLabel: 'Menunggu kasir',
        isConnected: false,
        isFullscreen: false,
        clockTime: '',
        clockDate: '',
        formatRp,
        formatFulfillment: value => ({ DINE_IN: 'Dine In', TAKEAWAY: 'Takeaway', DELIVERY: 'Delivery' })[value] ?? '',
        formatPayment: value => ({ CASH: 'Tunai', QRIS: 'QRIS', CARD: 'Kartu', OTHER: 'Lainnya' })[value] ?? 'Tunai',

        init() {
            bus = transport(config, ({ type, payload }) => {
                if (type === 'CFD_SYNC' || type === 'CFD_ACK' || type === 'CFD_CASHIER_READY') {
                    localSeenAt = Date.now();
                    this.isConnected = true;
                    this.syncLabel = 'Terhubung lokal';
                    if (type === 'CFD_SYNC') this.receiveSnapshot(payload);
                    if (type !== 'CFD_ACK' && kiosk) this.deliverPending();
                }
            });
            if (kiosk) pending = bus.readPending();
            this.receiveSnapshot(bus.readState());
            this.refreshScreen();
            heartbeat = setInterval(() => this.refreshScreen(), pollInterval);
            this.updateClock();
            if (!kiosk) clock = setInterval(() => this.updateClock(), 1000);
            onFullscreenChange = () => { this.isFullscreen = Boolean(document.fullscreenElement); };
            document.addEventListener('fullscreenchange', onFullscreenChange);
            echo = window.Echo?.private(channelName).listen('.pos.display.updated', state => {
                if (!this.hasLocalCashier()) {
                    lastCloudAt = Date.now();
                    this.receiveSnapshot(state);
                }
            });
        },
        destroy() {
            clearInterval(heartbeat);
            clearInterval(clock);
            clearTimeout(paidTimer);
            bus?.close();
            if (echo) window.Echo.leave(channelName);
            document.removeEventListener('fullscreenchange', onFullscreenChange);
        },
        hasLocalCashier() {
            return localSeenAt > 0 && Date.now() - localSeenAt < pollInterval * 2;
        },
        async refreshScreen() {
            bus.send('CFD_HELLO');
            if (this.hasLocalCashier() || pollBusy) return;
            pollBusy = true;
            try {
                const { state } = await request(config.snapshotUrl);
                if (!this.hasLocalCashier() && state) {
                    lastCloudAt = Date.now();
                    this.receiveSnapshot(state);
                }
            } catch {
                // Keep the local cart and retry on the next heartbeat.
            } finally {
                pollBusy = false;
            }
            if (!this.hasLocalCashier()) {
                this.isConnected = lastCloudAt > Date.now() - pollInterval * 3;
                this.syncLabel = this.isConnected ? 'Terhubung lewat jaringan' : 'Menunggu kasir';
                if (latest && !freshState(latest)) {
                    latest = null;
                    this.renderSnapshot();
                }
            }
            if (kiosk) this.deliverPending();
        },
        receiveSnapshot(state) {
            if (state) {
                const ids = new Set(state.acknowledged_action_ids ?? []);
                if (kiosk && pending.some(entry => ids.has(entry.action.action_id))) {
                    pending = pending.filter(entry => !ids.has(entry.action.action_id));
                    bus.writePending(pending);
                }
                const older = latest && (state.cart_id === latest.cart_id
                    ? Number(state.revision) < Number(latest.revision)
                    : Date.parse(state.updated_at) < Date.parse(latest.updated_at));
                if (freshState(state) && !older) latest = state;
            }
            this.renderSnapshot();
        },
        renderSnapshot() {
            clearTimeout(paidTimer);
            if (latest?.status === 'paid') {
                const remaining = latest.paid_until - Date.now();
                if (remaining > 0) {
                    this.state = { ...idleState(), ...latest };
                    paidTimer = setTimeout(() => this.renderSnapshot(), remaining);
                    return;
                }
                latest = null;
            }
            const base = { ...idleState(), ...latest };
            if (!kiosk || base.status === 'payment') {
                this.state = base;
                return;
            }
            let rows = base.items;
            for (const { action, createdAt } of pending) {
                if (Date.now() - createdAt < stateLifetime && (!action.cart_id || action.cart_id === base.cart_id)) {
                    rows = applyCartAction(rows, action, selection => {
                        const item = cartItem(selection, products, salesModes);
                        return item ? displayItem(item) : null;
                    });
                }
            }
            const subtotal = rows.reduce((sum, row) => sum + Number(row.subtotal), 0);
            this.state = { ...base, items: rows, status: rows.length ? 'ordering' : 'idle', subtotal,
                grand_total: Math.max(0, subtotal - Number(base.discount_amount || 0)) };
        },
        async deliverPending() {
            if (sendBusy) return;
            if (this.hasLocalCashier()) {
                for (const { action } of pending) bus.send('CFD_CUSTOMER_ACTION', action);
                return;
            }
            sendBusy = true;
            try {
                for (const entry of [...pending]) {
                    if (this.hasLocalCashier()) break;
                    if (entry.sent || !pending.includes(entry) || Date.now() - entry.createdAt >= stateLifetime) continue;
                    try {
                        await request(config.actionUrl, entry.action);
                        entry.sent = true;
                        bus.writePending(pending);
                    } catch (error) {
                        if (error.status !== 422) break;
                        this.actionError = error.message;
                        pending = pending.filter(current => current !== entry);
                        bus.writePending(pending);
                        this.renderSnapshot();
                    }
                }
            } finally {
                sendBusy = false;
                if (this.hasLocalCashier() && pending.length) this.deliverPending();
            }
        },
        sendAction(action, fields = {}) {
            if (['payment', 'paid'].includes(this.state.status)) return;
            pending = pending.filter(entry => Date.now() - entry.createdAt < stateLifetime);
            if (pending.length >= 100) {
                this.actionError = 'Antrean kiosk penuh. Tunggu sampai keranjang tersambung ke kasir.';
                return;
            }
            this.actionError = '';
            pending.push({
                action: { action_id: window.newRequestId(), action, cart_id: this.state.cart_id ?? null, ...fields },
                createdAt: Date.now(),
            });
            bus.writePending(pending);
            this.renderSnapshot();
            // Give a same-browser cashier time to answer the initial handshake.
            if (this.hasLocalCashier()) this.deliverPending();
            else setTimeout(() => this.deliverPending(), 200);
        },
        updateClock() {
            const now = new Date();
            this.clockTime = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
            this.clockDate = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },
        async toggleFullscreen() {
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else await document.documentElement.requestFullscreen?.();
            } catch {
                // Some embedded browsers manage fullscreen themselves.
            }
        },
        get filteredProducts() {
            const term = this.search.trim().toLocaleLowerCase('id-ID');
            return this.products.filter(product => (!this.activeCategory || product.category_id === this.activeCategory)
                && (product.name.toLocaleLowerCase('id-ID').includes(term) || product.category_name.toLocaleLowerCase('id-ID').includes(term)));
        },
        get totalItemsCount() {
            return this.state.items.reduce((total, item) => total + Number(item.qty), 0);
        },
        handleProductTap(product) {
            const now = Date.now();
            if (product.is_sold_out || !salesModes.length || this.modifierModal.show || now - (tapTimes.get(product.id) ?? 0) < 250) return;
            this.actionError = '';
            tapTimes.set(product.id, now);
            const selectedModifiers = product.modifier_groups.flatMap(group => group.modifiers.slice(0, modifierBounds(group).min));
            this.modifierModal = { show: true, product, selectedVariant: product.variants[0] ?? null,
                selectedModifiers, servingType: salesModes[0], qty: 1, notes: '' };
        },
        get modalUnitTotal() {
            const { product, selectedVariant, selectedModifiers } = this.modifierModal;
            return Number(product?.effective_price || 0) + Number(selectedVariant?.price_adjustment || 0)
                + (selectedModifiers ?? []).reduce((sum, mod) => sum + Number(mod.price_adjustment), 0);
        },
        isModifierSelected(id) {
            return this.modifierModal.selectedModifiers.some(modifier => modifier.id === id);
        },
        selectModifierRadio(group, modifier) {
            const ids = group.modifiers.map(option => option.id);
            this.modifierModal.selectedModifiers = this.modifierModal.selectedModifiers.filter(option => !ids.includes(option.id));
            if (modifier) this.modifierModal.selectedModifiers.push(modifier);
        },
        toggleModifierMultiple(group, modifier, event) {
            const selected = this.modifierModal.selectedModifiers;
            const count = group.modifiers.filter(option => this.isModifierSelected(option.id)).length;
            if (this.isModifierSelected(modifier.id)) {
                this.modifierModal.selectedModifiers = selected.filter(option => option.id !== modifier.id);
            } else if (count < modifierBounds(group).max) {
                selected.push(modifier);
            } else {
                event.target.checked = false;
            }
        },
        confirmModifierAdd() {
            const modal = this.modifierModal;
            if (!modal.show) return;
            const item = { id: modal.product.id, line_key: window.newRequestId(), variant_id: modal.selectedVariant?.id ?? null,
                modifiers: modal.selectedModifiers.map(modifier => modifier.id), notes: modal.notes.trim(),
                serving_type: modal.servingType, qty: modal.qty };
            if (!cartItem(item, products, salesModes)) {
                this.actionError = 'Lengkapi pilihan varian dan jumlah tambahan yang diperlukan.';
                return;
            }
            modal.show = false;
            this.sendAction('add_item', { item });
        },
        updateQty(index, delta) {
            const line_key = this.state.items[index]?.line_key;
            if (line_key) this.sendAction('update_qty', { line_key, delta });
        },
        clearCart() {
            this.sendAction('clear_cart');
        },
    };
}

window.customerDisplayApp = config => screens(config, false);
window.customerKioskApp = config => screens(config, true);

window.posCustomerBridge = config => {
    const products = new Map(config.products.map(product => [product.id, product]));
    const salesModes = config.salesModes.filter(mode => ['DINE_IN', 'TAKEAWAY'].includes(mode));
    const channelName = displayChannel(config);
    let bus;
    let pollTimer;
    let updateTimer;
    let paidTimer;
    let cloudTimer;
    let echo;
    let revision = 0;
    let activeCartId;
    let lastSnapshot;
    let paidSnapshot;
    let cloudBusy = false;
    let cloudDirty = false;
    let pollBusy = false;
    let ready = false;
    let recoveryCartId;
    const deferred = new Map();
    const acknowledged = new Set();

    return {
        initCustomerDisplaySync() {
            if (this._cfdInitialized || !config.active) return;
            this._cfdInitialized = true;
            bus = transport(config, ({ type, payload }) => {
                if (type === 'CFD_HELLO') {
                    bus.send('CFD_ACK');
                    if (lastSnapshot) bus.send('CFD_SYNC', { ...lastSnapshot, updated_at: new Date().toISOString() });
                }
                if (type === 'CFD_CUSTOMER_ACTION') this.handleCustomerKioskAction(payload);
            });
            const stored = bus.readState();
            recoveryCartId = freshState(stored) && stored.status !== 'paid' ? stored.cart_id : null;
            activeCartId = window.newRequestId();
            for (const property of ['cart', 'discount', 'customerName', 'selectedTable', 'fulfillmentType', 'paymentModal', 'successScreen']) {
                this.$watch(property, () => this.scheduleCustomerDisplay());
            }
            this.$watch('paymentPending', () => this.scheduleCustomerDisplay());
            echo = window.Echo?.private(channelName).listen('.pos.customer.action', action => this.handleCustomerKioskAction(action, true));
            for (const id of stored?.acknowledged_action_ids ?? []) acknowledged.add(id);
            for (const { action } of bus.readPending()) this.handleCustomerKioskAction(action);
            this.scheduleCustomerDisplay();
            bus.send('CFD_CASHIER_READY');
            this.pollCustomerActions(true);
            pollTimer = setInterval(() => this.pollCustomerActions(), pollInterval);
        },
        destroy() {
            clearInterval(pollTimer);
            clearTimeout(updateTimer);
            clearTimeout(cloudTimer);
            clearTimeout(paidTimer);
            bus?.close();
            if (echo) window.Echo.leave(channelName);
        },
        async pollCustomerActions(initial = false) {
            if (pollBusy) return;
            pollBusy = true;
            try {
                const { actions, state } = await request(config.snapshotUrl);
                if (initial && freshState(state)) {
                    if (state.status !== 'paid' && state.cart_id) recoveryCartId ??= state.cart_id;
                    for (const id of state.acknowledged_action_ids ?? []) {
                        acknowledged.add(id);
                        if (acknowledged.size > 200) acknowledged.delete(acknowledged.values().next().value);
                    }
                }
                for (const action of actions) this.handleCustomerKioskAction(action, true);
            } catch {
                // Local customers can keep sending actions while offline.
            } finally {
                pollBusy = false;
                if (!ready) {
                    ready = true;
                    this.scheduleCustomerDisplay();
                }
            }
        },
        handleCustomerKioskAction(action, cloud = false) {
            if (!uuidV4.test(action?.action_id)) return;
            const processed = window.__oqariProcessedKioskActions ??= new Set();
            if (processed.has(action.action_id) || acknowledged.has(action.action_id)) {
                if (cloud && !acknowledged.has(action.action_id)) this.acknowledgeCustomerAction(action.action_id);
                return;
            }
            if (this.paymentModal.show || this.paymentPending || this.successScreen.show) {
                deferred.set(action.action_id, { ...action, cart_id: action.cart_id ?? activeCartId });
                return;
            }
            if (!action.cart_id || action.cart_id === activeCartId || action.cart_id === recoveryCartId) {
                const cart = applyCartAction(this.cart, action, selection => cartItem(selection, products, salesModes));
                if (action.action === 'clear_cart') {
                    this.clearCart();
                } else if (cart !== this.cart) {
                    if (this.cart.length === 0 && cart.length > 0 && salesModes.includes(cart[0].serving_type)) {
                        this.fulfillmentType = cart[0].serving_type;
                    }
                    this.cart = cart;
                }
            }
            processed.add(action.action_id);
            if (processed.size > 200) processed.delete(processed.values().next().value);
            this.acknowledgeCustomerAction(action.action_id);
        },
        acknowledgeCustomerAction(id) {
            acknowledged.add(id);
            if (acknowledged.size > 200) acknowledged.delete(acknowledged.values().next().value);
            this.scheduleCustomerDisplay();
        },
        customerDisplayDetails() {
            const fulfillment = this.paymentModal.show ? this.paymentModal.fulfillment : this.fulfillmentType;
            return {
                items: this.cart.map(item => {
                    item.line_key ??= window.newRequestId();
                    return displayItem(item);
                }),
                subtotal: Number(this.subtotal),
                discount_amount: Number(this.discountAmount),
                discount_label: this.discount.code || this.discount.reason || 'Diskon',
                grand_total: Number(this.grandTotal),
                customer_name: this.customerName || null,
                table_name: fulfillment === 'DINE_IN' ? this.selectedTable || null : null,
                fulfillment_type: fulfillment,
                payment_method: this.paymentModal.show ? this.paymentModal.method : null,
                amount_paid: this.paymentModal.show ? Number(this.paymentModal.amountPaid || 0) : null,
            };
        },
        recordCustomerPayment(order, amountPaid) {
            if (!bus) return;
            paidSnapshot = {
                ...this.customerDisplayDetails(),
                status: 'paid',
                grand_total: Number(order.total),
                payment_method: this.paymentModal.method,
                amount_paid: amountPaid,
                change: Number(order.change || 0),
                order_id: Number(order.order_id),
                paid_until: Date.now() + paidDuration,
            };
            recoveryCartId = null;
            activeCartId = window.newRequestId();
            clearTimeout(paidTimer);
            paidTimer = setTimeout(() => {
                if (paidSnapshot) {
                    paidSnapshot = null;
                    this.scheduleCustomerDisplay();
                }
            }, paidDuration);
        },
        scheduleCustomerDisplay() {
            if (!bus) return;
            clearTimeout(updateTimer);
            updateTimer = setTimeout(() => this.publishCustomerDisplay(), 0);
        },
        publishCustomerDisplay() {
            if (!this.paymentModal.show && !this.paymentPending && !this.successScreen.show && deferred.size) {
                const queued = [...deferred.values()];
                deferred.clear();
                for (const action of queued) this.handleCustomerKioskAction(action);
            }
            // Preserve the customer's receipt even if the cashier immediately clears the order.
            const details = paidSnapshot ?? (!this.successScreen.show && this.cart.length
                ? { ...this.customerDisplayDetails(), status: this.paymentModal.show ? 'payment' : 'ordering' }
                : idleState());
            if (lastSnapshot?.status === 'ordering' && details.status === 'idle') {
                activeCartId = window.newRequestId();
                recoveryCartId = null;
            }
            const state = {
                ...details, cart_id: activeCartId, revision: ++revision,
                acknowledged_action_ids: [...acknowledged], updated_at: new Date().toISOString(),
            };
            lastSnapshot = state;
            bus.writeState(state);
            bus.send('CFD_SYNC', state);
            cloudDirty = true;
            if (ready) {
                clearTimeout(cloudTimer);
                cloudTimer = setTimeout(() => this.flushCustomerDisplay(), 200);
            }
        },
        async flushCustomerDisplay() {
            if (!cloudDirty || cloudBusy) return;
            cloudBusy = true;
            const { updated_at, paid_until, ...state } = lastSnapshot;
            cloudDirty = false;
            try {
                await request(config.syncUrl, state);
            } catch (error) {
                if (error.status !== 422) cloudDirty = true;
            } finally {
                cloudBusy = false;
                if (cloudDirty) cloudTimer = setTimeout(() => this.flushCustomerDisplay(), pollInterval);
            }
        },
        async openCustomerDisplay(mode) {
            const url = mode === 'kiosk' ? config.kioskUrl : config.displayUrl;
            const popup = window.open(url, 'oqari-customer-screen-' + (config.terminalId ?? config.shopId), 'popup,width=1280,height=800');
            if (!popup) {
                this.notify('Izinkan pop-up untuk membuka layar pelanggan.', 'error');
                return;
            }
            try {
                if (window.screen.isExtended && window.getScreenDetails) {
                    const details = await window.getScreenDetails();
                    const secondary = details.screens.find(screen => screen !== details.currentScreen);
                    if (secondary && !popup.closed) {
                        popup.moveTo(secondary.availLeft, secondary.availTop);
                        popup.resizeTo(secondary.availWidth, secondary.availHeight);
                    }
                }
            } catch {
                // The popup still works on the current screen without permission.
            }
            popup.focus();
        },
    };
};

