<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
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
    private const XML_PATH_JWT_ALGORITHM = 'jwt_crossdomain_auth/security/jwt_algorithm';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
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
     * Get JWT secret key
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getSecretKey($storeId = null): string
    {
        // First try to get from config
        $configKey = $this->scopeConfig->getValue(
            self::XML_PATH_JWT_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($configKey) {
            return $this->encryptor->decrypt($configKey);
        }

        // Use Magento's encryption key as fallback
        $magentoKey = $this->encryptor->exportKeys();
        $mainKey = explode("\n", $magentoKey)[0]; // Use the first key

        // Ensure the key is at least 32 bytes for HS256
        if (strlen($mainKey) < 32) {
            return hash('sha256', $mainKey);
        }

        return $mainKey;
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

        return (int)$value ?: 3600; // Default to 1 hour (3600 seconds)
    }

    /**
     * Get the JWT algorithm to use
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getAlgorithm($storeId = null): string
    {
        $algorithm = $this->scopeConfig->getValue(
            self::XML_PATH_JWT_ALGORITHM,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $algorithm ?: 'HS256'; // Default to HMAC-SHA256
    }
}
