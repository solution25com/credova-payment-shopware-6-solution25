import template from './sw-settings-credova-list.html.twig';
import './sw-settings-credova-list.scss';
import '../../component';

const { Component, Context } = Shopware;

Component.register('sw-settings-credova-list', {
    template,

    mounted() {
        this.fetchShopwareVersion();
    },

    methods: {
        onButtonClick() {
            this.$refs.credovaForm.saveConfig();
        }
    }
});
