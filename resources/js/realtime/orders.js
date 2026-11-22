export function initOrderNotificationEngine(shopId, branchId = null, branchesEnabled = false) {
    if (typeof window.Echo === "undefined") {
        console.warn("Laravel Echo is not loaded.");
        return;
    }

    if (branchesEnabled && !branchId) {
        return;
    }

    const channelName = 'shop.' + shopId + (branchId ? '.branch.' + branchId : '') + '.orders';
    console.log("[Notification Engine] Initializing channel:", channelName);

    window.Echo.private(channelName)
        .stopListening(".order.created")
        .listen(".order.created", (e) => {
            if (branchId && e.branch_id && Number(e.branch_id) !== Number(branchId)) {
                return;
            }
            console.log("[Notification Engine] OrderCreated event received!", e);
            // Payload is already lean (e.g. e.id, e.order_number, e.total)
            window.dispatchEvent(new CustomEvent("new-order-received", { 
                detail: e 
            }));
        });
}

window.initOrderNotificationEngine = initOrderNotificationEngine;

document.addEventListener("DOMContentLoaded", () => {
    const realtime = window.OQARI_REALTIME || {};
    if (realtime.shopId) {
        initOrderNotificationEngine(realtime.shopId, realtime.branchId ?? null, Boolean(realtime.branchesEnabled));
    }
});
