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
        'underscore',
        'mage/template'
    ],
    function ($, _, mageTemplate) {
        'use strict';

        var formTmpl = '<form action="<%= data.action %>"' +
            ' method="POST" hidden enctype="application/x-www-form-urlencoded">' +
            '<% _.each(data.fields, function(val, key){ %>' +
            '<input value=\'<%= val %>\' name="<%= key %>" type="hidden">' +
            '<% }); %>' +
            '</form>';

        return function (response) {

            var hiddenFormTmpl = mageTemplate(formTmpl);

            return $(hiddenFormTmpl({
                data: {
                    action: response[0].action,
                    fields: response[0].fields
                }
            })).appendTo($('[data-container="body"]'));

        };
    }
);
