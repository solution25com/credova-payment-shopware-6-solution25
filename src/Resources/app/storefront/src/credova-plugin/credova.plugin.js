const { PluginBaseClass } = window;

export default class CredovaPlugin extends PluginBaseClass {
    static options = {
        selector: '[data-credova="1"]',
    };

    init() {
        if (!window.CRDV) {
            document.addEventListener('readystatechange', () => {
                if (document.readyState === 'complete') this._boot();
            });
        } else {
            this._boot();
        }
    }

    _boot() {
        const nodes = Array.from(document.querySelectorAll(this.options.selector));
        if (!nodes.length) return;

        nodes.forEach((node) => this._wireNode(node));
    }

    _wireNode(node) {
        if (node.dataset.credovaBound === '1') return;
        node.dataset.credovaBound = '1';

        const envStr = (node.dataset.environment || 'sandbox').toLowerCase();
        const env =
            envStr === 'production' ? CRDV.Environment.Production : CRDV.Environment.Sandbox;

        const storeCode = node.dataset.storeCode;
        const currency = node.dataset.currency || 'USD';

        CRDV.plugin.config({ environment: env, store: storeCode, currency });

        CRDV.plugin.inject('credova-button');

        if (!window.__credovaApprovedListener) {
            CRDV.plugin.addEventListener((e) => {
                if (e.eventName !== CRDV.EVENT_USER_WAS_APPROVED) return;

                const raw = e.eventArgs?.publicId;
                const publicId = Array.isArray(raw) ? raw[0] : raw;
                if (!publicId || typeof publicId !== 'string') return;

                const anchor = document.querySelector('[data-credova="1"]');
                this._sendPublicId(publicId, anchor).catch((err) => {
                    console.warn('Failed to persist Credova publicId:', err);
                });
            });
            window.__credovaApprovedListener = true;
        }

    }

    async _sendPublicId(publicId, nodeForHeaders) {
        const accessKey = nodeForHeaders?.dataset.swAccessKey || '';
        const ctxToken = nodeForHeaders?.dataset.swContextToken || '';

        const res = await fetch('/store-api/credova/public-id', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(accessKey ? { 'sw-access-key': accessKey } : {}),
                ...(ctxToken ? { 'sw-context-token': ctxToken } : {}),
            },
            body: JSON.stringify({ publicId }),
            credentials: 'same-origin',
        });

        if (!res.ok) {
            const text = await res.text().catch(() => '');
            throw new Error(`Store API ${res.status}: ${text || 'Unknown error'}`);
        }
        return res.json();
    }
}
