define([
    'jquery',
    'mage/loader'
], function ($) {
    'use strict';
    
    return function (config) {
        $(document).ready(function () {
            $('#cross-domain-switch').on('click', function (e) {
                e.preventDefault();
                
                // Show loader
                $('body').loader('show');
                
                // Make AJAX call to get token
                $.ajax({
                    url: config.tokenUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        // Redirect to the crossdomain login endpoint with the token
                        if (response.token) {
                            window.location.href = config.crossDomainUrl + '?token=' + response.token;
                        } else {
                            // Hide loader if error
                            $('body').loader('hide');
                            console.error('Token not received');
                        }
                    },
                    error: function (xhr, status, error) {
                        // Hide loader if error
                        $('body').loader('hide');
                        console.error('Error fetching token: ' + error);
                    }
                });
            });
        });
    };
});