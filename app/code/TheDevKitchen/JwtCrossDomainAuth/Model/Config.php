<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration model for JWT Cross-Domain Authentication
 */
class Config
{
    private const XML_PATH_ENABLED = 'jwt_crossdomain_auth/general/enabled';
    private const XML_PATH_TARGET_DOMAIN = 'jwt_crossdomain_auth/general/target_domain';
    private const XML_PATH_JWT_SECRET = 'jwt_crossdomain_auth/security/jwt_secret';
    private const XML_PATH_JWT_EXPIRATION = 'jwt_crossdomain_auth/security/jwt_expiration';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the module is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return (bool)$this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get target domain for cross-domain authentication
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getTargetDomain($storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_TARGET_DOMAIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get token expiration time in seconds
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getJwtExpiration($storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_JWT_EXPIRATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (int)$value ?: 300; // Default to 5 minutes (300 seconds)
    }
}
