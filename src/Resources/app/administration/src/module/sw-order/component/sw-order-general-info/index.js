import template from './sw-order-general-info.html.twig'
import './sw-order-general-info.scss';

const {Component} = Shopware;
Component.override('sw-order-general-info',
    {
        template,
        methods: {
            getBackgroundStyle() {
                const technicalName = this.transaction.stateMachineState.technicalName;
                switch (technicalName) {
                    case 'credova_signed':
                        return 'sw-order-state__credova-signed-select';
                    case 'credova_approved':
                        return 'sw-order-state__credova-approved-select';
                    default:
                        return 'sw-order-state__open-select';
                }
            },
        },


    });