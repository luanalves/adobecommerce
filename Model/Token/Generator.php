<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Token;

use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * JWT Token Generator
 */
class Generator
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
     * @param JwtManagerInterface $jwtManager
     * @param Config $config
     */
    public function __construct(
        JwtManagerInterface $jwtManager,
        Config $config
    ) {
        $this->jwtManager = $jwtManager;
        $this->config = $config;
    }
    
    /**
     * Generate a JWT token
     *
     * @param array $payload Data to include in the token
     * @param int|null $lifetime Token lifetime in seconds (overrides config)
     * @return string
     * @throws LocalizedException
     */
    public function generate(array $payload, ?int $lifetime = null): string
    {
        try {
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
     * Encode to base64url (URL-safe base64)
     *
     * @param string $input
     * @return string
     */
    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}