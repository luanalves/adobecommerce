define([
    'jquery'
], function ($) {
    'use strict';

    return function (originalWidget) {
        $.widget('mage.header', originalWidget, {
            _create: function () {
                this._super();
            }
        });

        return $.mage.header;
    };
});
