<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Service;

use Magento\Framework\Exception\LocalizedException;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Generator;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Validator;

/**
 * Main service class for JWT Token operations
 * Provides high-level interface for token generation and validation
 * Acts as a facade for the Generator and Validator components
 */
class TokenService implements TokenServiceInterface
{
    /**
     * Token generation service
     * @var Generator
     */
    private $generator;

    /**
     * Token validation service
     * @var Validator
     */
    private $validator;

    /**
     * Module configuration
     * @var Config
     */
    private $config;

    /**
     * Constructor
     *
     * @param Generator $generator Token generation service
     * @param Validator $validator Token validation service
     * @param Config $config Module configuration
     */
    public function __construct(
        Generator $generator,
        Validator $validator,
        Config $config
    ) {
        $this->generator = $generator;
        $this->validator = $validator;
        $this->config = $config;
    }

    /**
     * Generate a new JWT token
     * Validates module status and delegates token creation to Generator
     *
     * @param array $payload Token payload containing customer data and claims
     * @param int|null $lifetime Optional token lifetime in seconds
     * @return string Generated JWT token
     * @throws LocalizedException If module is disabled or generation fails
     */
    public function generateToken(array $payload, ?int $lifetime = null): string
    {
        $this->validateModuleEnabled();
        return $this->generator->generate($payload, $lifetime);
    }

    /**
     * Validate a JWT token and extract its claims
     * Validates module status and delegates validation to Validator
     *
     * @param string $token JWT token to validate
     * @return array Token claims if validation successful
     * @throws LocalizedException If module is disabled or validation fails
     */
    public function validateToken(string $token): array
    {
        $this->validateModuleEnabled();
        return $this->validator->validate($token);
    }

    /**
     * Validates that the module is enabled
     * Centralized module status check for all service operations
     *
     * @throws LocalizedException if module is disabled
     * @return void
     */
    private function validateModuleEnabled(): void
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Cross-domain authentication is disabled.'));
        }
    }
}
