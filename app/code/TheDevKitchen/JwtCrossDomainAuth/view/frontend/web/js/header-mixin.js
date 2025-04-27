define([
    'jquery'
], function ($) {
    'use strict';

    return function (originalWidget) {
        $.widget('mage.header', originalWidget, {
            _create: function () {
                this._super();
                
                // The crossDomainSwitch component is now initialized via x-magento-init
                // in the template file, so we don't need to initialize it here
            }
        });

        return $.mage.header;
    };
});