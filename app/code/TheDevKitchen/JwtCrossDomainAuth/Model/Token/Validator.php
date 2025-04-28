<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Token;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Jwt\JwtManagerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * JWT Token Validator
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
     * Validate a JWT token
     *
     * @param string $token
     * @return array Claims from the token
     * @throws LocalizedException If validation fails
     */
    public function validate(string $token): array
    {
        try {
            // Manual JWT validation
            $tokenParts = explode('.', $token);
            if (count($tokenParts) != 3) {
                throw new LocalizedException(__('Invalid token format'));
            }
            
            list($headerBase64, $payloadBase64, $signatureBase64) = $tokenParts;
            
            // Verify signature
            $secretKey = $this->config->getSecretKey();
            $signature = hash_hmac('sha256', "$headerBase64.$payloadBase64", $secretKey, true);
            $calculatedSignature = base64_encode($signature);
            
            if ($this->base64UrlDecode($signatureBase64) !== $this->base64UrlDecode($calculatedSignature)) {
                throw new LocalizedException(__('Token signature verification failed'));
            }
            
            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payloadBase64), true);
            if (!is_array($payload)) {
                throw new LocalizedException(__('Invalid token payload'));
            }
            
            // Check expiration
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
     * Decode base64url encoded string
     *
     * @param string $input
     * @return string
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