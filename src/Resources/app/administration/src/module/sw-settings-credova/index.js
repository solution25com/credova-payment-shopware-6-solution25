import './page/sw-settings-credova-list';

const { Module, Context } = Shopware;

function compareVersions(v1, v2) {
    const a = v1.split('.').map(Number);
    const b = v2.split('.').map(Number);
    for (let i = 0; i < Math.max(a.length, b.length); i++) {
        if ((a[i] || 0) > (b[i] || 0)) return 1;
        if ((a[i] || 0) < (b[i] || 0)) return -1;
    }
    return 0;
}

const currentVersion = Context.app.config.version;
console.log('Current Shopware Version:', currentVersion);

if (compareVersions(currentVersion, '6.7.0') >= 0) {
    Module.register('sw-settings-credova', {
        type: 'plugin',
        name: 'CredovaSettings',
        description: 'sw-settings-credova.general.descriptionTextModule',
        version: '1.0.0',
        targetVersion: '1.0.0',
        icon: 'regular-cog',
        favicon: 'icon-module-settings.png',
        routes: {
            index: {
                component: 'sw-settings-credova-list',
                path: 'index',
            }
        },
        settingsItem: {
            group: 'credova',
            to: 'sw.settings.credova.index',
            icon: 'regular-credit-card',
            label: 'sw-settings-credova.general.mainMenuItemGeneral'
        }
    });
} else if (compareVersions(currentVersion, '6.6.0') >= 0) {
    Module.register('sw-settings-credova', {
        type: 'plugin',
        name: 'sw-settings-credova',
        title: 'Credova Settings',
        description: 'Manage settings for Credova integration',
        color: '#000000',
        icon: 'regular-cog',
        routes: {
            index: {
                component: 'sw-settings-credova-list',
                path: 'index'
            }
        },
        settingsItem: {
            group: 'system',
            to: 'sw.settings.credova.index',
            icon: 'regular-cog'
        }
    });
} else {
    console.warn('Shopware version below 6.6.0 is not supported by this module.');
}
