import template from './sw-settings-credova-form.html.twig';
import './sw-settings-credova-form.scss';
const { Component, Mixin } = Shopware;

Component.register('sw-settings-credova-form', {
    template,
    inject: ['systemConfigApiService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            salesChannelId: null, 
            config: {},
            configs: {},
            popupTypeOptions: [
                { value: 'iframe', label: this.$tc('sw-settings-credova.options.popupType.iframe') },
                { value: 'popup', label: this.$tc('sw-settings-credova.options.popupType.popup') }
            ],
            checkoutFlowOptions: [
                { value: 'auth_capture', label: this.$tc('sw-settings-credova.options.checkoutFlow.auth_capture') },
                { value: 'auth_only', label: this.$tc('sw-settings-credova.options.checkoutFlow.auth_only') },
                { value: 'order_first', label: this.$tc('sw-settings-credova.options.checkoutFlow.order_first')}
            ]
        };
    },    

    created() {
        this.salesChannelId = null;
        this.loadConfig();
    },

    methods: {
        async onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            await this.loadConfig();
        },

        async loadConfig() {
            try {

                const config = await this.systemConfigApiService.getConfig('core.basicInformation');
                // The shop name is usually in the config as:
                // config[0].elements.find(e => e.name === 'core.basicInformation.shopName')
                const shopNameField = config[0].elements.find(
                    el => el.name === 'core.basicInformation.shopName'
                );
                this.shopName = shopNameField ? shopNameField.defaultValue : null;
                // Or, get the value (not schema) with getValues:
                const valuesa = await this.systemConfigApiService.getValues('core.basicInformation', null);
                this.shopName = valuesa['core.basicInformation.shopName'];
                console.log('Config loaded:', this.shopName);
                const values = await this.systemConfigApiService.getValues('Credova.config', this.salesChannelId);
                this.config = { ...values };

                if (this.config.popupType !== undefined) {
                    this.config.popupType = String(this.config.popupType);
                }

                if (this.config.checkoutFlow !== undefined) {
                    this.config.checkoutFlow = String(this.config.checkoutFlow);
                }
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('sw-settings-credova.notification.loadError.title'),
                    message: this.$tc('sw-settings-credova.notification.loadError.message')
                });                
            }
        },

        async saveConfig() {
            const min = Number(this.config['Credova.config.minFinanceAmount']);
            const max = Number(this.config['Credova.config.maxFinanceAmount']);
        
            if (min < 300 || min > 5000 || max < 300 || max > 5000) {
                this.createNotificationWarning({
                    title: this.$tc('sw-settings-credova.notification.validation.title'),
                    message: this.$tc('sw-settings-credova.notification.validation.message')
                });
                return;
            }            
        
            try {
                await this.systemConfigApiService.saveValues(this.config, this.salesChannelId);
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-credova.notification.saveSuccess.title'),
                    message: this.$tc('sw-settings-credova.notification.saveSuccess.message')
                });
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('sw-settings-credova.notification.saveError.title'),
                    message: this.$tc('sw-settings-credova.notification.saveError.message')
                });                
            }
        }
    }
});
