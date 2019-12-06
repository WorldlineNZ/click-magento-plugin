/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Onfire_Paymark/js/action/paymark-redirect'
    ],
    function (Component, paymarkRedirectAction) {
        'use strict';

        var paymarkConfig = window.checkoutConfig.payment.paymark;

        return Component.extend({
            clickLogo: paymarkConfig.logo,

            defaults: {
                template: 'Onfire_Paymark/payment/click'
            },

            redirectAfterPlaceOrder: false,

            getCode: function() {
                return 'paymark';
            },

            getData: function() {
                return {
                    'method': this.item.method
                };
            },

            afterPlaceOrder: function() {
                paymarkRedirectAction(this.messageContainer);
            }
        });
    }
);