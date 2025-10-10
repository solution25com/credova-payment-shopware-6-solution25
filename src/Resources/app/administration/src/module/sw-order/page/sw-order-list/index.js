import template from './sw-order-list.html.twig'
import './sw-order-list.scss';
const {Component} = Shopware

Component.override('sw-order-list', {
    template,
    methods: {
        getStatusBadgeClass(technicalName) {
            switch (technicalName) {
                case 'credova_approved':
                    return 'sw-color-badge--credova-approved';
                case 'credova_signed':
                    return 'sw-color-badge--credova-signed';
                case 'in_progress':
                    return 'sw-color-badge--in-progress';
                case 'completed':
                    return 'sw-color-badge--completed';
                case 'cancelled':
                    return 'sw-color-badge--cancelled';
                case 'failed':
                    return 'sw-color-badge--cancelled';
                case 'paid':
                    return 'sw-color-badge--completed';
                default:
                    return '';
            }
        },

        getVariantFromPaymentState(order) {
            let technicalName = order.transactions.last().stateMachineState.technicalName;
            // set the payment status to the first transaction that is not cancelled
            for (let i = 0; i < order.transactions.length; i += 1) {
                if (!['cancelled', 'failed'].includes(order.transactions[i].stateMachineState.technicalName)) {
                    technicalName = order.transactions[i].stateMachineState.technicalName;
                    break;
                }
            }


            switch (technicalName) {
                case 'in_progress':
                    return 'primary';
                case 'completed':
                    return 'success';
                case 'cancelled':
                    return 'danger';
                case 'paid':
                    return 'success';
                case 'failed':
                    return 'danger';
                case 'refund':
                    return 'primary';
                case 'open':
                    return 'neutral';
                case 'credova_approved':
                    return 'primary';
                case 'credova_signed':
                    return 'success';
                default:
                    return 'neutral';
            }
        }
    },
})

