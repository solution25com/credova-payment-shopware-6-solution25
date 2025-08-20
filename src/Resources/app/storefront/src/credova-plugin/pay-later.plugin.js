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

        this.publicId = this.parentWrapper.getAttribute('data-public-id');
        this.options.storeCode = this.parentWrapper.getAttribute('data-store-code');
        this.options.environment = this.parentWrapper.getAttribute('data-mode');

        this.credovaService = new CredovaService({
            environment: this.options.environment,
            storeCode: this.options.storeCode,
            publicId: this.publicId
        });
    }

    init() {
        // this._registerElements();
        // this._registerEvents();
    }

    _registerEvents() {
        if (!this.confirmOrderForm) {
            console.warn('PayLaterPlugin: confirm form not found');
            return;
        }

        this.confirmOrderForm.addEventListener('submit', (e) => {
            e.preventDefault();

            this.credovaService.checkout().then((completed) => {
                if (completed) {
                    console.log('Checkout completed, submitting order');
                    this.confirmOrderForm.submit();
                } else {
                    console.log('Checkout was closed, order not submitted');
                }
            });
        });
    }
}
