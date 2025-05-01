<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration model for JWT Cross-Domain Authentication
 * Handles all configuration settings and security-related parameters
 */
class Config
{
    /**
     * Configuration path for module enabled status
     */
    private const XML_PATH_ENABLED = 'jwt_crossdomain_auth/general/enabled';

    /**
     * Configuration path for logging enabled status
     */
    private const XML_PATH_LOGGING_ENABLED = 'jwt_crossdomain_auth/logging/enabled';

    /**
     * Configuration path for log file name
     */
    private const XML_PATH_LOG_FILE = 'jwt_crossdomain_auth/logging/log_file';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Constructor
     * 
     * @param ScopeConfigInterface $scopeConfig Core configuration interface
     * @param EncryptorInterface $encryptor Encryption service for secure data
     * @param DeploymentConfig $deploymentConfig Deployment configuration reader
     * @param UrlInterface $urlBuilder URL generation service
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        DeploymentConfig $deploymentConfig,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->deploymentConfig = $deploymentConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Check if the module is enabled
     * Verifies if cross-domain authentication functionality is active
     *
     * @param int|string|null $storeId Store view ID to check configuration for
     * @return bool True if module is enabled, false otherwise
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
     * Check if logging is enabled
     * Verifies if logging functionality is active
     *
     * @param int|string|null $storeId Store view ID to check configuration for
     * @return bool True if logging is enabled, false otherwise
     */
    public function isLoggingEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && 
            (bool)$this->scopeConfig->isSetFlag(
                self::XML_PATH_LOGGING_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * Get configured log file name
     * Returns the log file name for storing logs
     *
     * @param int|string|null $storeId Store view ID to get log file name for
     * @return string|null Log file name or null if not configured
     */
    public function getLogFileName($storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LOG_FILE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get target domain for JWT token audience
     * Returns the base domain of the current store for bidirectional authentication
     *
     * @param int|string|null $storeId Store view ID to get domain for
     * @return string Base domain URL without path
     */
    public function getTargetDomain($storeId = null): string
    {
        return parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * Get JWT expiration time in seconds
     * Defines how long a token remains valid after generation
     *
     * @param int|string|null $storeId Store view ID for configuration
     * @return int Expiration time in seconds (default: 60 seconds)
     */
    public function getJwtExpiration($storeId = null): int
    {
        return 60;
    }

    /**
     * Get JWT secret key using Magento's native encryption key
     * This method centralizes security logic for token generation and validation
     *
     * @param int|string|null $storeId Store view ID for configuration
     * @return string Secret key for JWT signing
     */
    public function getSecretKey($storeId = null): string
    {
        // Try to get key from deployment config first (more efficient)
        $cryptKey = $this->deploymentConfig->get('crypt/key');

        // Fallback to encryptor if not found in deployment config
        if (!$cryptKey) {
            $cryptKey = $this->encryptor->getKey();
        }

        // Ensure we have a secure key of at least 32 bytes
        if (!$cryptKey || strlen($cryptKey) < 32) {
            // Use SHA-256 to guarantee appropriate length
            $cryptKey = hash('sha256', $cryptKey ?: 'magento-jwt-default');
        }

        return $cryptKey;
    }
}
