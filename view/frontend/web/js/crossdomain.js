define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';
    
    return function (config) {
        // Initialize on document ready
        $(function () {
            console.log('Cross-domain switcher initialized with config:', config);
            
            // Find and attach click handler to all cross-domain links
            $('.cross-domain-link').on('click', function (e) {
                e.preventDefault();
                
                // Get target domain and store information from data attributes
                var targetDomain = $(this).data('domain');
                var storeId = $(this).data('store-id');
                var storeName = $(this).text().trim();
                
                console.log('Cross-domain link clicked:', {
                    targetDomain: targetDomain,
                    storeId: storeId,
                    storeName: storeName
                });
                
                if (!targetDomain) {
                    console.error('Target domain not specified');
                    return;
                }
                
                // Show loader with custom CSS class
                if (!$('.crossdomain-loading-mask').length) {
                    $('body').append(
                        '<div class="crossdomain-loading-mask">' +
                        '    <div class="loader">' +
                        '        <img src="' + require.toUrl('images/loader-1.gif') + '" alt="' + $t('Loading...') + '">' +
                        '    </div>' +
                        '</div>'
                    );
                } else {
                    $('.crossdomain-loading-mask').show();
                }
                
                // Log request details for debugging
                console.log('Making AJAX request to:', config.tokenUrl, {
                    storeId: storeId
                });
                
                // Make AJAX call to get JWT token
                $.ajax({
                    url: config.tokenUrl,
                    type: 'GET',
                    dataType: 'json',
                    cache: false,
                    data: {
                        store_id: storeId
                    },
                    success: function (response) {
                        console.log('Token response received:', response);
                        
                        if (response.success && response.token) {
                            console.log('Token received successfully, redirecting to:', targetDomain);
                            
                            // Construct target URL with token
                            var targetUrl = targetDomain + '/jwt/login?token=' + encodeURIComponent(response.token);
                            console.log('Full redirect URL:', targetUrl);
                            
                            // Redirect to the new domain with the token
                            window.location.href = targetUrl;
                        } else {
                            // Hide loader if error
                            $('.crossdomain-loading-mask').hide();
                            
                            // Log error details
                            console.error('Token generation failed:', response);
                            
                            // Show error message
                            if (response.message) {
                                alert($t('Error: ') + response.message);
                            } else {
                                alert($t('An error occurred while preparing to switch stores. Please try again.'));
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        // Hide loader if error
                        $('.crossdomain-loading-mask').hide();
                        
                        // Log detailed error information
                        console.error('AJAX error details:', {
                            status: status,
                            statusCode: xhr.status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        
                        // Special handling for different error types
                        var errorMessage;
                        
                        if (xhr.status === 404) {
                            errorMessage = $t('The token generation endpoint could not be found. Please verify the module configuration.');
                        } else if (xhr.status === 500) {
                            errorMessage = $t('A server error occurred.');
                        } else if (status === 'timeout') {
                            errorMessage = $t('The request timed out. Please try again or contact customer service.');
                        } else if (status === 'parsererror') {
                            errorMessage = $t('The server response could not be parsed. Please try again later.');
                        } else {
                            // Try to parse JSON response
                            errorMessage = $t('An error occurred while switching stores. Please try again.');
                            try {
                                var responseJson = JSON.parse(xhr.responseText);
                                console.log('Parsed error response:', responseJson);
                                if (responseJson.message) {
                                    errorMessage = responseJson.message;
                                }
                            } catch (e) {
                                console.error('Error parsing error response:', e);
                            }
                        }
                        
                        // Show more detailed error message
                        alert($t('Error: ') + errorMessage + ' (' + xhr.status + ' ' + error + ')');
                    }
                });
            });
        });
    };
});