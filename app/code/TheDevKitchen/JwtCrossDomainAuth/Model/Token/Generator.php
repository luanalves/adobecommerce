<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Token;

use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * JWT Token Generator
 * Handles the creation of secure JSON Web Tokens for cross-domain authentication
 */
class Generator
{
    /**
     * Magento's JWT Manager for token operations
     * @var JwtManagerInterface
     */
    private $jwtManager;
    
    /**
     * Module configuration provider
     * @var Config
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param JwtManagerInterface $jwtManager JWT service for token operations
     * @param Config $config Module configuration access
     */
    public function __construct(
        JwtManagerInterface $jwtManager,
        Config $config
    ) {
        $this->jwtManager = $jwtManager;
        $this->config = $config;
    }

    /**
     * Validates that the module is enabled before executing operations
     * Security check to prevent token generation when module is disabled
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
     * Generate a JWT token with specified payload and optional lifetime
     * Creates a secure token containing customer information for cross-domain auth
     *
     * @param array $payload Data to include in the token (customer info, etc)
     * @param int|null $lifetime Token lifetime in seconds (overrides config)
     * @return string Generated JWT token string
     * @throws LocalizedException If token generation fails
     */
    public function generate(array $payload, ?int $lifetime = null): string
    {
        try {
            // Check if module is enabled first
            $this->validateModuleEnabled();

            // Add standard claims
            $issuedAt = time();
            $expiration = $issuedAt + ($lifetime ?? $this->config->getJwtExpiration());
            
            $finalPayload = array_merge($payload, [
                'iat' => $issuedAt,
                'exp' => $expiration
            ]);
            
            // Get the secret key for signing
            $secretKey = $this->config->getSecretKey();
            
            // Manual JWT creation with base64url encoding
            $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
            $payloadEncoded = $this->base64UrlEncode(json_encode($finalPayload));
            $signature = hash_hmac('sha256', "$header.$payloadEncoded", $secretKey, true);
            $signatureEncoded = $this->base64UrlEncode($signature);
            
            return "$header.$payloadEncoded.$signatureEncoded";
        } catch (\Exception $e) {
            throw new LocalizedException(__('Error generating token: %1', $e->getMessage()));
        }
    }
    
    /**
     * Encode data to base64url format (URL-safe base64)
     * Converts binary data to a format safe for URLs without padding
     *
     * @param string $input Raw data to encode
     * @return string URL-safe base64 encoded string
     */
    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}