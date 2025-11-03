import './module/sw-settings-credova'
import './module/sw-order/component/sw-order-general-info'
import './module/sw-order/page/sw-order-list'
import './component/credova-api-test';

import CredovaApiTestService from './service/credova-api-test.service';

Shopware.Service().register('credovaApiTestService', () => {
    return new CredovaApiTestService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});