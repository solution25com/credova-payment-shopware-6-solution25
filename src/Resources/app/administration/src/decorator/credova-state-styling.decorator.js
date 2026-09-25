const { Application } = Shopware;

Application.addServiceProviderDecorator('stateStyleDataProviderService', (stateStyleService) => {
    stateStyleService.addStyle('order_transaction.state', 'credova_approved', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_transaction.state', 'credova_signed', {
        icon: 'done',
        color: 'done',
        variant: 'done',
    });

    return stateStyleService;
});
