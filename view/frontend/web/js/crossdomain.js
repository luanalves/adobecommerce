/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Frontend JavaScript handler for cross-domain store switching
 * Manages the UI interaction and token acquisition process
 */

define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';
    
    return function (config) {
        // Initialize handler when document is ready
        $(function () {
            console.log('Cross-domain switcher initialized with config:', config);
            
            // Use event delegation to handle clicks on cross-domain links
            $('[data-role=cross-domain-container]').on('click', '.cross-domain-link', function (e) {
                e.preventDefault();
                
                // Extract target store information from data attributes
                var targetDomain = $(this).data('domain');
                var storeId = $(this).data('store-id');
                var storeName = $(this).text().trim();
                
                console.log('Cross-domain link clicked:', {
                    targetDomain: targetDomain,
                    storeId: storeId,
                    storeName: storeName
                });

                // Validate required data
                if (!targetDomain) {
                    console.error('Target domain not specified');
                    return;
                }
                
                // Create and display loading indicator
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
                
                // Log token request details for debugging
                console.log('Requesting authentication token:', {
                    url: config.tokenUrl,
                    storeId: storeId
                });
                
                // Request JWT token for cross-domain authentication
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
                            console.log('Token received, redirecting to target domain');
                            
                            // Build target URL with authentication token
                            var targetUrl = targetDomain + '/jwt/login?token=' + encodeURIComponent(response.token);
                            console.log('Redirect URL prepared:', targetUrl);
                            
                            // Perform the redirect with the token
                            window.location.href = targetUrl;
                        } else {
                            // Handle error response
                            $('.crossdomain-loading-mask').hide();
                            
                            console.error('Token generation failed:', response);
                            
                            // Display appropriate error message
                            if (response.message) {
                                alert($t('Error: ') + response.message);
                            } else {
                                alert($t('An error occurred while preparing to switch stores. Please try again.'));
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        // Hide loading indicator on error
                        $('.crossdomain-loading-mask').hide();
                        
                        // Log detailed error information for debugging
                        console.error('AJAX request failed:', {
                            status: status,
                            statusCode: xhr.status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        
                        // Handle different error scenarios
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
                            // Default error handling
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
                        
                        // Display error message to user
                        alert($t('Error: ') + errorMessage + ' (' + xhr.status + ' ' + error + ')');
                    }
                });
            });
        });
    };
});