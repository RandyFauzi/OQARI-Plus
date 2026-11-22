const { test } = require('node:test');
const assert = require('node:assert/strict');
const { randomUUID } = require('node:crypto');
const { readFileSync } = require('node:fs');
const { setTimeout: delay } = require('node:timers/promises');
const vm = require('node:vm');
const { join } = require('node:path');

const source = readFileSync(join(__dirname, '../../resources/js/realtime/customer-display.js'), 'utf8');
const jawara = {
    id: 1, name: 'Jawara', category_name: 'Burger', price: 16000, effective_price: 16000,
    variants: [],
    modifier_groups: [{
        id: 2, selection_type: 'MULTIPLE', max_selections: 2,
        modifiers: [{ id: 3, name: 'Daging', price_adjustment: 10000 }],
    }],
};
const config = {
    shopId: 1, active: true, products: [jawara], salesModes: ['DINE_IN', 'TAKEAWAY'],
    actionUrl: '/action', syncUrl: '/sync', snapshotUrl: '/snapshot',
};

function screens() {
    const storage = new Map();
    const channels = new Set();
    const delivered = [];
    const cloud = { state: null, actions: [] };
    const browser = vm.createContext({
        window: { newRequestId: randomUUID, addEventListener() {}, removeEventListener() {} },
        document: { querySelector: () => ({ content: 'csrf' }), addEventListener() {}, removeEventListener() {} },
        localStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        setTimeout, clearTimeout, setInterval, clearInterval,
        BroadcastChannel: class {
            constructor(name) {
                this.name = name;
                channels.add(this);
            }

            postMessage(data) {
                for (const channel of channels) {
                    if (channel !== this && channel.name === this.name) queueMicrotask(() => channel.onmessage({ data }));
                }
            }

            close() { channels.delete(this); }
        },
        fetch: async (url, options) => {
            if (options.body) delivered.push({ url, body: JSON.parse(options.body) });
            return { ok: true, json: async () => cloud };
        },
    });
    vm.runInContext(source, browser);

    return {
        cloud, delivered,
        kiosk: () => browser.window.customerKioskApp(config),
        display: () => browser.window.customerDisplayApp(config),
        cashier: () => ({
            ...browser.window.posCustomerBridge(config),
            cart: [], discount: {}, discountAmount: 0, fulfillmentType: 'DINE_IN',
            paymentModal: { show: false }, successScreen: { show: false },
            $watch() {},
            clearCart() { this.cart = []; this.recalledOrderId = null; },
            get subtotal() { return this.cart.reduce((sum, item) => sum + item.subtotal, 0); },
            get grandTotal() { return this.subtotal; },
        }),
    };
}

function takeOrder(kiosk) {
    kiosk.handleProductTap(jawara);
    kiosk.toggleModifierMultiple(jawara.modifier_groups[0], jawara.modifier_groups[0].modifiers[0]);
    kiosk.modifierModal.servingType = 'TAKEAWAY';
    kiosk.modifierModal.notes = 'Tanpa tomat';
    kiosk.confirmModifierAdd();
    kiosk.handleProductTap(jawara);
    assert.equal(kiosk.modifierModal.show, false, 'a second tap within 250ms is ignored');
}

async function until(predicate) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (predicate()) return;
        await delay(10);
    }
    assert.fail('The screens did not synchronize');
}

test('a kiosk with a local cashier sends Jawara and Daging once without posting an action', async t => {
    const app = screens();
    const kiosk = app.kiosk();
    const cashier = app.cashier();
    t.after(() => { kiosk.destroy(); cashier.destroy(); });
    cashier.initCustomerDisplaySync();
    kiosk.init();
    await until(() => kiosk.hasLocalCashier());
    takeOrder(kiosk);
    await until(() => cashier.cart.length === 1 && kiosk.state.items.length === 1);
    assert.equal(cashier.cart[0].modifiers[0].name, 'Daging');
    assert.equal(cashier.cart[0].notes, 'Tanpa tomat');
    assert.equal(cashier.cart[0].price, 26000);
    assert.equal(cashier.fulfillmentType, 'TAKEAWAY');

    kiosk.updateQty(0, 1);
    await until(() => cashier.cart[0].qty === 2);
    await until(() => app.delivered.some(entry => entry.url === '/sync'));
    assert.equal(app.delivered.filter(entry => entry.url === '/action').length, 0);
    assert.equal(cashier.cart[0].subtotal, 52000);

    cashier.recalledOrderId = 45;
    kiosk.clearCart();
    await until(() => cashier.cart.length === 0);
    assert.equal(cashier.recalledOrderId, null);
});

test('a kiosk can queue before cashier opens and replaying the same local and cloud actions does not increment twice', async t => {
    const app = screens();
    const kiosk = app.kiosk();
    const cashier = app.cashier();
    t.after(() => { kiosk.destroy(); cashier.destroy(); });
    kiosk.init();
    takeOrder(kiosk);
    kiosk.updateQty(0, 1);
    assert.equal(kiosk.state.grand_total, 52000);
    await until(() => app.delivered.filter(entry => entry.url === '/action').length === 2);
    app.cloud.actions = app.delivered.filter(entry => entry.url === '/action').map(entry => entry.body);

    cashier.initCustomerDisplaySync();
    cashier.initCustomerDisplaySync();
    await until(() => kiosk.hasLocalCashier() && kiosk.state.items[0]?.qty === 2);
    for (const action of app.cloud.actions) cashier.handleCustomerKioskAction(action, true);
    assert.equal(cashier.cart.length, 1);
    assert.equal(cashier.cart[0].qty, 2);
});

test('the display shows payment, then keeps the receipt visible when the cashier clears the cart', async t => {
    const app = screens();
    const kiosk = app.kiosk();
    const cashier = app.cashier();
    const display = app.display();
    t.after(() => { display.destroy(); kiosk.destroy(); cashier.destroy(); });
    cashier.initCustomerDisplaySync();
    display.init();
    kiosk.init();
    await until(() => kiosk.hasLocalCashier());
    takeOrder(kiosk);
    await until(() => display.state.status === 'ordering');
    cashier.paymentModal = { show: true, method: 'CASH', amountPaid: 50000, fulfillment: 'TAKEAWAY' };
    cashier.scheduleCustomerDisplay();
    await until(() => display.state.status === 'payment');
    assert.equal(display.state.grand_total, 26000);

    cashier.recordCustomerPayment({ total: 26000, change: 24000, order_id: 12 }, 50000);
    cashier.paymentModal.show = false;
    cashier.cart = [];
    cashier.scheduleCustomerDisplay();
    await until(() => display.state.status === 'paid');
    assert.equal(display.state.order_id, 12);
    assert.equal(display.state.change, 24000);
    assert.equal(display.state.items[0].name, 'Jawara');
    assert.ok(display.state.paid_until - Date.now() > 9000);
});

