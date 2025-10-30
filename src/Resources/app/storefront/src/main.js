const PluginManager = window.PluginManager;

PluginManager.register(
    'CredovaPlugin',
    () => import('./credova-plugin/credova.plugin'),
    '[data-credova="1"]'
);
PluginManager.register(
    'PayLaterPlugin',
    () => import('./credova-plugin/pay-later.plugin'),
    '[credova-payment-pay-later]'
);