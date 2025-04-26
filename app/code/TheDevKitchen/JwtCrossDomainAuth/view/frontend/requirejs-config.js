var config = {
    map: {
        '*': {
            crossDomainSwitch: 'TheDevKitchen_JwtCrossDomainAuth/js/crossdomain'
        }
    },
    config: {
        mixins: {
            'Magento_Theme/js/view/header': {
                'TheDevKitchen_JwtCrossDomainAuth/js/header-mixin': true
            }
        }
    }
};