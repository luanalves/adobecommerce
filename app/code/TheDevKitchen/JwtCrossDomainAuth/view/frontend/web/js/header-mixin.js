define([
    'jquery',
    'crossDomainSwitch'
], function ($, crossDomainSwitch) {
    'use strict';

    return function (originalWidget) {
        $.widget('mage.header', originalWidget, {
            _create: function () {
                this._super();

                // Initialize the cross-domain switch functionality
                crossDomainSwitch({
                    tokenUrl: '/crossdomain/token',
                    crossDomainUrl: '/crossdomain/login'
                });
            }
        });

        return $.mage.header;
    };
});