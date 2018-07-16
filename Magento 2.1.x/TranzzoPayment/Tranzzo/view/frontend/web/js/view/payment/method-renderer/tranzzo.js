define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/url-builder',
        'mage/url'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        urlBuilder,
        url
    ){
        'use strict';

        return Component.extend({
            defaults: {
                template: 'TranzzoPayment_Tranzzo/payment/checkout-tranzzo'
            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                this.afterPlaceOrder.bind(this);
                var self = this, placeOrder;

                placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
                $.when(placeOrder).fail(function () {
                    self.isPlaceOrderActionAllowed(true);
                }).done(this.afterPlaceOrder.bind(this));
                return true;
            },
            afterPlaceOrder: function () {
                console.log('Redirect Tranzzo')
                window.location.replace(url.build('tranzzo/checkout/redirect'));
            }
        });
    }
);