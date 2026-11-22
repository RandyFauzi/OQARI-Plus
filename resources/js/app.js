import Alpine from 'alpinejs';
import { sidebarScroll } from './sidebar-scroll';
import './realtime/customer-display';

window.oqariBranchUrl = path => {
    const prefix = document.querySelector('meta[name="oqari-branch-prefix"]')?.content;
    return prefix && /^\/(?:admin|shift)\//.test(path) ? prefix + path : path;
};

window.oqariFetch = (path, options) => fetch(window.oqariBranchUrl(path), options);

window.newRequestId = function () {
    if (crypto.randomUUID) {
        return crypto.randomUUID();
    }
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
};

window.Alpine = Alpine;
Alpine.data('sidebarScroll', sidebarScroll);

Alpine.start();

import './echo';

import './realtime/orders';
