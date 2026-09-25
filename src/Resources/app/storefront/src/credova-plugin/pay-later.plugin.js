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
    }
}
