<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const XML_PATH_ENABLED = 'jwt_crossdomain_auth/general/enabled';
    const XML_PATH_TARGET_DOMAIN = 'jwt_crossdomain_auth/general/target_domain';
    const XML_PATH_JWT_SECRET = 'jwt_crossdomain_auth/security/jwt_secret';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the module is enabled
     *
     * @param null|int $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Get target domain for cross-domain authentication
     *
     * @param null|int $storeId
     * @return string
     */
    public function getTargetDomain($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TARGET_DOMAIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get JWT secret key
     *
     * @param null|int $storeId
     * @return string
     */
    public function getJwtSecret($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_JWT_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}