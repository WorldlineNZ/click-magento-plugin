define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/customer-data'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader, customerData) {

        'use strict';

        return function (messageContainer) {
            customerData.invalidate(['cart']);
            fullScreenLoader.startLoader();

            var module = 'paymark';

            if (!customer.isLoggedIn()) {
                var url = '/guest-carts/:module/redirect';
            } else {
                var url = '/carts/mine/:module/redirect';
            }

            var serviceUrl = urlBuilder.createUrl(url, {module: module});

            return storage.get(serviceUrl)
                .done(function (redirectUrl) {
                    $.mage.redirect(redirectUrl);
                })
                .fail(function (response) {
                    fullScreenLoader.stopLoader();
                    errorProcessor.process(response, messageContainer);
                });
        };
    });
