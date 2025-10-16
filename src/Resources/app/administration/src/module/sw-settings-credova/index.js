import './page/sw-settings-credova-list';
import './component/sw-settings-credova-icon'
const { Module } = Shopware;


    Module.register('sw-settings-credova', {
        type: 'plugin',
        name: 'sw-settings-credova',
        title: 'Credova Settings',
        description: 'Manage settings for Credova integration',
        color: '#000000',
        iconComponent: 'sw-settings-credova-icon',
        routes: {
            index: {
                component: 'sw-settings-credova-list',
                path: 'index'
            }
        },
        settingsItem: {
        group: 'plugins',
        to: 'sw.settings.credova.index',
        backgroundEnabled: false,
        iconComponent: 'sw-settings-credova-icon'
        }
    });

