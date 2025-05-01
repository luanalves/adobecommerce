<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Token;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Jwt\JwtManagerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * JWT Token Validator
 * Handles validation and verification of JWT tokens for cross-domain authentication
 * Performs security checks including signature verification and expiration validation
 */
class Validator
{
    /**
     * @var JwtManagerInterface
     */
    private $jwtManager;
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param JwtManagerInterface $jwtManager JWT service for token operations
     * @param Config $config Module configuration and security settings
     */
    public function __construct(
        JwtManagerInterface $jwtManager,
        Config $config
    ) {
        $this->jwtManager = $jwtManager;
        $this->config = $config;
    }

    /**
     * Validates that the module is enabled
     * Security check to prevent token validation when module is disabled
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
    
    /**
     * Validate a JWT token and extract its claims
     * Performs comprehensive token validation including:
     * - Token format validation
     * - Signature verification
     * - Expiration check
     * - Payload structure verification
     *
     * @param string $token JWT token to validate
     * @return array Validated claims from the token
     * @throws LocalizedException If validation fails for any reason
     */
    public function validate(string $token): array
    {
        try {
            // Check if module is enabled first
            $this->validateModuleEnabled();

            // Manual JWT validation
            $tokenParts = explode('.', $token);
            if (count($tokenParts) != 3) {
                throw new LocalizedException(__('Invalid token format'));
            }
            
            list($headerBase64, $payloadBase64, $signatureBase64) = $tokenParts;
            
            // Verify signature using HMAC-SHA256
            $secretKey = $this->config->getSecretKey();
            $signature = hash_hmac('sha256', "$headerBase64.$payloadBase64", $secretKey, true);
            $calculatedSignature = base64_encode($signature);
            
            if ($this->base64UrlDecode($signatureBase64) !== $this->base64UrlDecode($calculatedSignature)) {
                throw new LocalizedException(__('Token signature verification failed'));
            }
            
            // Decode and validate payload structure
            $payload = json_decode($this->base64UrlDecode($payloadBase64), true);
            if (!is_array($payload)) {
                throw new LocalizedException(__('Invalid token payload'));
            }
            
            // Verify token expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new LocalizedException(__('Token has expired'));
            }
            
            return $payload;
        } catch (\Exception $e) {
            if ($e instanceof LocalizedException) {
                throw $e;
            }
            throw new LocalizedException(__('Error validating token: %1', $e->getMessage()));
        }
    }
    
    /**
     * Decode a base64url encoded string
     * Handles URL-safe base64 encoding with proper padding restoration
     *
     * @param string $input Base64url encoded string
     * @return string Decoded data
     */
    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}