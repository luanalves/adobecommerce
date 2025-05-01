/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Header mixin for cross-domain authentication functionality
 * Extends the default header widget to add cross-domain switching capabilities
 */

define([
    'jquery'
], function ($) {
    'use strict';

    return function (originalWidget) {
        $.widget('mage.header', originalWidget, {
            /**
             * Override _create method to add cross-domain functionality
             * Maintains original header behavior while adding store switching support
             */
            _create: function () {
                // Call parent implementation first
                this._super();
                
                // Additional cross-domain initialization can be added here
            }
        });

        return $.mage.header;
    };
});
