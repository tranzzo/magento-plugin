define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tranzzo',
                component: 'TranzzoPayment_Tranzzo/js/view/payment/method-renderer/tranzzo'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);