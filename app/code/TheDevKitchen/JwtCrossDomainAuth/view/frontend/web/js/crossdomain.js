define([
    'jquery',
    'mage/loader'
], function ($) {
    'use strict';
    
    return function (config) {
        // Initialize on document ready
        $(function () {
            // Find and attach click handler to the cross-domain switch link
            $('#cross-domain-switch').on('click', function (e) {
                e.preventDefault();
                console.log('Cross-domain link clicked!');
                
                // Show loader
                $('body').loader('show');
                
                // Make AJAX call to get token
                $.ajax({
                    url: config.tokenUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        console.log('Token received:', response);
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