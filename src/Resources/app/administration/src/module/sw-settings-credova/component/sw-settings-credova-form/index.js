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
                { value: 'popup',  label: this.$tc('sw-settings-credova.options.popupType.popup') }
            ],
            checkoutFlowOptions: [
                { value: 'auth_capture', label: this.$tc('sw-settings-credova.options.checkoutFlow.auth_capture') },
                { value: 'auth_only',    label: this.$tc('sw-settings-credova.options.checkoutFlow.auth_only') },
                { value: 'order_first',  label: this.$tc('sw-settings-credova.options.checkoutFlow.order_first') }
            ]
        };
    },
    computed: {
        isCustomTextFilled() {
            const value = this.config?.['Credova.config.dataMessage'] ?? '';
            return typeof value === 'string' && value.trim().length > 0;
        }
    },

    created() {
        this.salesChannelId = null;
        this.loadConfig();
    },

    watch: {
        config: {
            handler() {
                this.enforceLogoRule();
            },
            deep: true
        }
    },

    methods: {
        enforceLogoRule() {
            if (this.isCustomTextFilled) {
                if (this.config['Credova.config.showCredovaLogo'] !== true) {
                    this.$set(this.config, 'Credova.config.showCredovaLogo', true);
                }
            }
        },

        async onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            await this.loadConfig();
        },

        async loadConfig() {
            try {
                const config = await this.systemConfigApiService.getConfig('core.basicInformation');
                const shopNameField = config[0].elements.find(el => el.name === 'core.basicInformation.shopName');
                this.shopName = shopNameField ? shopNameField.defaultValue : null;

                const values = await this.systemConfigApiService.getValues('Credova.config', this.salesChannelId);
                this.config = { ...values };

                if (this.config['Credova.config.popupType'] !== undefined) {
                    this.config['Credova.config.popupType'] = String(this.config['Credova.config.popupType']);
                }
                if (this.config['Credova.config.checkoutFlow'] !== undefined) {
                    this.config['Credova.config.checkoutFlow'] = String(this.config['Credova.config.checkoutFlow']);
                }
                if (this.config['Credova.config.dataMessage'] == null) {
                    this.$set(this.config, 'Credova.config.dataMessage', '');
                }

                this.enforceLogoRule();
            } catch {
                this.createNotificationError({
                    title: this.$tc('sw-settings-credova.notification.loadError.title'),
                    message: this.$tc('sw-settings-credova.notification.loadError.message')
                });
            }
        },

        async saveConfig() {
            const min = Number(this.config['Credova.config.minFinanceAmount']);
            const max = Number(this.config['Credova.config.maxFinanceAmount']);
            const dataMessage = this.config['Credova.config.dataMessage'];
            if(typeof dataMessage === 'string' && dataMessage.trim().length > 0) {
                this.createNotificationWarning({
                    title: this.$tc('warning'),
                    message: this.$tc('sw-settings-credova.help.logoForcedByCustomText')
                });
            }

            if (min < 300 || min > 5000 || max < 300 || max > 5000) {
                this.createNotificationWarning({
                    title: this.$tc('sw-settings-credova.notification.validation.title'),
                    message: this.$tc('sw-settings-credova.notification.validation.message')
                });
                return;
            }

            this.enforceLogoRule();

            const payload = { ...this.config };
            const dmKey = 'Credova.config.dataMessage';
            const dmVal = payload[dmKey];

            if (typeof dmVal === 'string' && dmVal.trim() === '') {
                delete payload[dmKey];
            }

            try {
                await this.systemConfigApiService.saveValues(payload, this.salesChannelId);
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-credova.notification.saveSuccess.title'),
                    message: this.$tc('sw-settings-credova.notification.saveSuccess.message')
                });
            } catch {
                this.createNotificationError({
                    title: this.$tc('sw-settings-credova.notification.saveError.title'),
                    message: this.$tc('sw-settings-credova.notification.saveError.message')
                });
            }
        }
    }
});