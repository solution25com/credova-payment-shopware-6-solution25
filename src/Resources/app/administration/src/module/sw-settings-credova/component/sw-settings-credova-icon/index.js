import template from './sw-settings-credova-icon.html.twig';
import * as  CredovaIcon from './credova.svg';

Shopware.Component.register('sw-settings-credova-icon', {
    template: template,
    data() {
        return {
            svgData: CredovaIcon,
            shopwareVersion: Shopware.Context.app.config.version
        }
    },
    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
        width() {
            return this.shopwareVersion.startsWith('6.7') ? 24 : 256;
        },
        height() {
            return this.shopwareVersion.startsWith('6.7') ? 24 : 256;
        },
    }
});
Shopware.Component.override('sw-icon', {
    watch: {
        name: {
            async handler(newName) {
                if (newName.indexOf('stripe-shopware-payment-icon') !== 0) {
                    return;
                }

                // The watcher is called (for the first and in normal cases only time) before the beforeMount hook of
                // the component is executed. Unfortunately shopware resets the rendered svg string in this hook,
                // therefore need to wait for the next tick to ensure that our svg string gets rendered.
                //
                // eslint-disable-next-line max-len,vue/max-len
                // See https://github.com/shopware/platform/blob/acc85ca70ac44e22ebe1e6e2a370a6d92384083c/src/Administration/Resources/app/administration/src/app/component/base/sw-icon/index.js#L122
                //
                // This workaround will be removed as soon as sw-icon will support loading of third party icons
                // See https://github.com/pickware/shopware-plugins/issues/3924
                await this.$nextTick();
                switch (newName) {
                    case 'credova':
                        this.iconSvgData = CredovaIcon;
                        break;
                    default:
                        this.iconSvgData = '';
                }
            },
            immediate: true,
        },
    },
});