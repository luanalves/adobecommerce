/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * RequireJS configuration for cross-domain authentication module
 * Maps module-specific JavaScript components for dependency injection
 */

var config = {
    map: {
        '*': {
            // Map the crossDomainSwitch component to its implementation
            crossDomainSwitch: 'TheDevKitchen_JwtCrossDomainAuth/js/crossdomain',
            loader: 'mage/loader'
        }
    }
};
