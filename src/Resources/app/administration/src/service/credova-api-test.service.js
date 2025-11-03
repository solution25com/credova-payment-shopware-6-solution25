const ApiService = Shopware.Classes.ApiService;

export default class CredovaApiTestService extends ApiService {

    constructor(httpClient, loginService, apiEndpoint = 'credova-test-connection') {
        super(httpClient, loginService, apiEndpoint);
    }
    check() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/test-connection`, {}, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}