export default class CredovaService {
    constructor({ environment = 'sandbox', storeCode, publicId }) {
        this.storeCode = storeCode;
        this.publicId = publicId;
        this.environment = environment?.toLowerCase() === 'sandbox'
            ? CRDV.Environment.Sandbox
            : CRDV.Environment.Production;
    }

    configure() {
        CRDV.plugin.config({
            environment: this.environment,
            store: this.storeCode
        });
    }

    checkout() {
        this.configure();
    }
}
