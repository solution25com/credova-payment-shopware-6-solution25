import template from './credova-api-test.html.twig';

const { Component, Mixin } = Shopware;

Component.register('credova-api-test', {
    template,

    inject: ['credovaApiTestService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;

            this.credovaApiTestService
                .check()
                .then((response) => {
                    const results = response.details || {};

                    const successes = [];
                    const errors = [];

                    for (const [name, ok] of Object.entries(results)) {
                        if (ok) {
                            successes.push(name);
                        } else {
                            errors.push(name);
                        }
                    }

                    if(successes.length && !errors.length){
                        this.createNotificationSuccess({
                            title: this.$tc('Credova.apiTest.success.title'),
                            message: this.$tc('Credova.apiTest.success.notification-message')
                        });
                    }
                    else{
                        if (successes.length) {
                            this.createNotificationSuccess({
                                title: this.$tc('Credova.apiTest.success.title'),
                                message: `${this.$tc('Credova.apiTest.success.notification-specific-channel')} ${successes.join(', ')}`,
                            });
                        }
                        if (errors.length) {
                            this.createNotificationError({
                                title: this.$tc('Credova.apiTest.error.title'),
                                message: `${this.$tc('Credova.apiTest.error.notification-specific')} ${errors.join(', ')}`,
                            });
                        }
                    }

                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('Credova.apiTest.fallbackError.title'),
                        message: error.response?.data?.errors?.[0]?.detail
                            || error.message
                            || this.$tc('Credova.apiTest.fallbackError.message'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
