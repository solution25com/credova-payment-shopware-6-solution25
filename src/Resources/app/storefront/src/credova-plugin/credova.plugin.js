import Plugin from 'src/plugin-system/plugin.class';

export default class CredovaPlugin extends Plugin {
    static options = {
        scriptSrc: 'https://plugin.credova.com/plugin.min.js',
        environment: 'Sandbox',
        storeCode: 'JRH000',
        buttonSelector: '#payLaterButton',
        formSelector: 'form[name="confirmOrderForm"]',
        prequalAmount: null,
        checkoutId: null
    };

    init() {
        this._hydrateOptionsFromDataset();

        this._ensureScript()
            .then(() => {
                this._configureCredova();
                this._bindEvents();
            })
            .catch((err) => console.error('[Credova] Failed to initialize:', err));
    }

    _hydrateOptionsFromDataset() {
        const ds = this.el.dataset || {};
    
        this.options.storeCode = ds.credovaStore || this.options.storeCode;
        this.options.environment = ds.credovaEnvironment || this.options.environment;
        this.options.prequalAmount = this._toNumber(ds.credovaPrequalAmount, this.options.prequalAmount);
        this.options.checkoutId = ds.credovaCheckoutId || this.options.checkoutId;
        this.options.buttonSelector = ds.credovaButtonSelector || this.options.buttonSelector;
        this.options.formSelector = ds.credovaFormSelector || this.options.formSelector;
    }
    

    _toNumber(val, fallback = null) {
        if (val === undefined || val === null || val === '') return fallback;
        const n = Number(val);
        return Number.isFinite(n) ? n : fallback;
    }

    _ensureScript() {
        if (window.CRDV && window.CRDV.plugin) return Promise.resolve();

        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${this.options.scriptSrc}"]`);
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', reject);
                return;
            }

            const s = document.createElement('script');
            s.src = this.options.scriptSrc;
            s.async = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('Failed to load Credova script'));
            document.head.appendChild(s);
        });
    }

    _configureCredova() {
        if (!window.CRDV || !window.CRDV.plugin || !window.CRDV.Environment) {
            throw new Error('Credova (CRDV) SDK not available');
        }
        const envEnum = window.CRDV.Environment[this.options.environment] ?? window.CRDV.Environment.Sandbox;

        window.CRDV.plugin.config({
            environment: envEnum,
            store: this.options.storeCode
        });
    }

    _bindEvents() {
        const btn  = this.el.querySelector(this.options.buttonSelector);
        if (btn && this.options.prequalAmount != null) {
            this._onPrequalClick = (e) => this._onPrequal(e);
            btn.addEventListener('click', this._onPrequalClick);
        }
    
        const form = this.el.closest('form') || this.el.querySelector(this.options.formSelector);
        if (form && this.options.checkoutId) {
            this._onFormSubmitHandler = (e) => this._onFormSubmit(e, form);
            form.addEventListener('submit', this._onFormSubmitHandler, { once: true });
        }
    }

    _onPrequal(e) {
        e.preventDefault();
        
        window.CRDV.plugin
            .prequalify(this.options.prequalAmount)
            .then((res) => {
                if (res.approved) {
                    alert(`User was approved and publicId is ${res.publicId}`);
                } else {
                    alert('User was not approved');
                }
            })
            .catch((err) => console.error('[Credova] prequalify error:', err));
    }

    destroy() {
        const btn  = this.el.querySelector(this.options.buttonSelector);
        if (btn && this._onPrequalClick) btn.removeEventListener('click', this._onPrequalClick);
    
        const form = this.el.closest('form') || this.el.querySelector(this.options.formSelector);
        if (form && this._onFormSubmitHandler) form.removeEventListener('submit', this._onFormSubmitHandler);
    
        super.destroy();
    }
    
    _onFormSubmit(e, form) {
        e.preventDefault();

        window.CRDV.plugin
            .checkout(this.options.checkoutId)
            .then((complete) => {
                console.log('[Credova] checkout complete:', complete);
                form.submit();
            })
            .catch((error) => {
                console.error('[Credova] checkout error:', error);
            })
            .finally(() => {
                console.log('[Credova] checkout finished');
            });
    }
}
