import CredovaService from '../credova-service/CredovaService';

export default class PayLaterPlugin extends window.PluginBaseClass {
    static options = {
        confirmFormId: 'confirmOrderForm',
        parentWrapperId: 'credova-payment-pay-later',
        storeCode: null,
        environment: null,
    };

    _registerElements() {
        this.confirmOrderForm = document.forms[this.options.confirmFormId];
        this.parentWrapper = document.getElementById(this.options.parentWrapperId);

        this.publicId = this.parentWrapper?.getAttribute('data-public-id');
        this.options.storeCode = this.parentWrapper?.getAttribute('data-store-code');
        this.options.environment = this.parentWrapper?.getAttribute('data-mode');

        this.credovaService = new CredovaService({
            environment: this.options.environment,
            storeCode: this.options.storeCode,
            publicId: this.publicId
        });
    }

    init() {
        this._registerElements();
        this._registerEvents();
    }

    _registerEvents() {
        this._initAccordion();
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
