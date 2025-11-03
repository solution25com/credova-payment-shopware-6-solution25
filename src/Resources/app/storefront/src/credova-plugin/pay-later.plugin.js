 

export default class PayLaterPlugin extends window.PluginBaseClass {
    static options = {
        confirmFormSelector: 'form[name="confirmOrderForm"]',
    };

    _registerElements() {
        this.confirmOrderForm = document.querySelector(this.options.confirmFormSelector);
        this.publicId = this.el?.dataset.publicId || null;
        this.storeCode = this.el?.dataset.credovaStoreCode || this.el?.dataset.storeCode || null;
        this.environment = this.el?.dataset.credovaMode || this.el?.dataset.mode || 'sandbox';

        
    }

    init() {
        this._registerElements();
        this._registerEvents();
    }

    _registerEvents() {
        this._initAccordion();
        this._ensureCredovaScript();
    }

    _initAccordion() {
        const accordions = document.querySelectorAll('.accordion');

        accordions.forEach(button => {
            button.addEventListener('click', function () {
                this.classList.toggle('active');

                const panel = this.nextElementSibling;
                const isOpen = panel.style.display === 'block';

                panel.style.display = isOpen ? 'none' : 'block';
            });
        });
    }
}

PayLaterPlugin.prototype._ensureCredovaScript = function() {
    if (window.CRDV) return;
    if (document.querySelector('script[data-credova-script="1"]')) return;
    const s = document.createElement('script');
    s.src = 'https://plugin.credova.com/plugin.min.js';
    s.async = true;
    s.defer = true;
    s.setAttribute('data-credova-script', '1');
    document.head.appendChild(s);
};
