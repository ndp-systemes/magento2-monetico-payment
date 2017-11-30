/**
 * NDP_Monetico extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License ("OSL") v. 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category       NDP
 * @package        NDP_Monetico
 * @copyright      Copyright (c) 2017
 * @author         NDP Systèmes
 * @license        Open Software License ("OSL") v. 3.0
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'NDP_Monetico/js/view/payment/form-builder'
    ],
    function ($, Component,urlBuilder, formBuilder) {
        'use strict';

        return Component.extend({

            defaults: {
                template: 'NDP_Monetico/payment/monetico',
                redirectAfterPlaceOrder: false
            },

            afterPlaceOrder: function (url) {

                var self = this;
                $.get('/monetico/payment/redirect/')
                    .done(function (response) {
                        formBuilder(response).submit();
                    })
                    .fail(function (response) {
                        errorProcessor.process(response, self.messageContainer);
                }).always(function () {
                    fullScreenLoader.stopLoader();
                });
            }
        });
    }
);