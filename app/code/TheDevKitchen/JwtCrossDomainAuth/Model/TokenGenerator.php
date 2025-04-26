<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;

class TokenGenerator
{
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;
    
    /**
     * @var DateTime
     */
    private $dateTime;
    
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param DateTime $dateTime
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        DateTime $dateTime,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->dateTime = $dateTime;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Generate a JWT token for cross-domain authentication
     *
     * @return string
     */
    public function generateToken()
    {
        // Get current timestamp for JWT expiration
        $currentTime = $this->dateTime->timestamp();
        $expirationTime = $currentTime + 300; // Token valid for 5 minutes
        
        // Prepare JWT payload
        $payload = [
            'iss' => $this->urlBuilder->getBaseUrl(),            // Issuer (this domain)
            'aud' => $this->config->getTargetDomain(),           // Audience (target domain)
            'iat' => $currentTime,                               // Issued at
            'exp' => $expirationTime,                            // Expiration time
            'jti' => bin2hex(random_bytes(16)),                  // JWT ID (random unique identifier)
        ];
        
        // If customer is logged in, include customer data
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $payload['sub'] = $customer->getId();                // Subject (customer ID)
            $payload['email'] = $customer->getEmail();
            $payload['name'] = $customer->getName();
        }
        
        // Encode the JWT token parts
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->generateSignature($header, $payload, $this->config->getJwtSecret());
        
        // Combine JWT token parts
        return $header . '.' . $payload . '.' . $signature;
    }
    
    /**
     * Base64 URL encode a string
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    /**
     * Generate HMAC-SHA256 signature for JWT token
     *
     * @param string $header
     * @param string $payload
     * @param string $secret
     * @return string
     */
    private function generateSignature($header, $payload, $secret)
    {
        $signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        return $this->base64UrlEncode($signature);
    }
}