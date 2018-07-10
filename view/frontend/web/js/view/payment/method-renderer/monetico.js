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
        'NDP_Monetico/js/view/payment/form-builder',
        'ndp_quickview',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor'
    ],
    function ($, Component,urlBuilder, formBuilder, quickview, fullScreenLoader, errorProcessor) {
        'use strict';

        return Component.extend({

            defaults: {
                template: 'NDP_Monetico/payment/monetico',
                redirectAfterPlaceOrder: false
            },

            afterPlaceOrder: function (url) {

                var saveCcInput = $("#monetico_savecb").prop('checked') == true ? 1 : 0;
                var self = this;
                $.get('/monetico/payment/redirect/', { saveCc : saveCcInput })
                    .done(function (response) {
                        formBuilder(response).submit();
                    })
                    .fail(function (response) {
                        errorProcessor.process(response, self.messageContainer);
                }).always(function () {
                    fullScreenLoader.stopLoader();
                });
            },
            getSaveCc: function () {
                return window.checkoutConfig.customer_config.save_cc == 1 ? true : false;
            },
            showCcModal: function (data, event) {
                quickview.displayContent("\\payment_methods", false);
            }
        });
    }
);