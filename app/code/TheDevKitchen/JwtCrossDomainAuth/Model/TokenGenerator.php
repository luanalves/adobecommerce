<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Jwt\Claim\Audience;
use Magento\Framework\Jwt\Claim\ExpirationTime;
use Magento\Framework\Jwt\Claim\IssuedAt;
use Magento\Framework\Jwt\Claim\Issuer;
use Magento\Framework\Jwt\Claim\JwtId;
use Magento\Framework\Jwt\Claim\PrivateClaim;
use Magento\Framework\Jwt\Claim\Subject;
use Magento\Framework\Jwt\Jwk;
use Magento\Framework\Jwt\Jws\Jws;
use Magento\Framework\Jwt\Jws\JwsHeader;
use Magento\Framework\Jwt\Jws\JwsSignatureJwks;
use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Jwt\Payload\ClaimsPayload;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates JWT tokens for cross-domain authentication
 */
class TokenGenerator
{
    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var JwtManagerInterface
     */
    private $jwtManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @param DateTime $dateTime
     * @param UrlInterface $urlBuilder
     * @param JwtManagerInterface $jwtManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        DateTime $dateTime,
        UrlInterface $urlBuilder,
        JwtManagerInterface $jwtManager,
        Config $config,
        LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->dateTime = $dateTime;
        $this->urlBuilder = $urlBuilder;
        $this->jwtManager = $jwtManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
    }

    /**
     * Generate a JWT token for a customer
     *
     * @param CustomerInterface|Customer|mixed $customer
     * @return string
     * @throws LocalizedException
     */
    public function generateTokenForCustomer($customer): string
    {
        // Check if $customer is an object with the required methods
        if (!is_object($customer)) {
            $this->logger->error('Invalid customer data provided: ' . var_export($customer, true));
            throw new LocalizedException(__('Cannot generate token: Invalid customer data provided.'));
        }

        if (!method_exists($customer, 'getId') || !$customer->getId()) {
            throw new LocalizedException(__('Cannot generate token for invalid customer.'));
        }

        // Get current timestamp for JWT expiration
        $currentTime = $this->dateTime->timestamp();
        $expirationTime = $currentTime + $this->config->getJwtExpiration();

        $this->logger->debug('Generating JWT token for customer: ' . $customer->getId());
        $this->logger->debug('Token will expire at: ' . date('Y-m-d H:i:s', $expirationTime));

        try {
            // Create DateTime objects for timestamps
            $currentDateTime = new \DateTime();
            $expirationDateTime = new \DateTime();
            $expirationDateTime->setTimestamp($expirationTime);

            // Safely get customer data
            $customerId = $customer->getId();
            $customerEmail = method_exists($customer, 'getEmail') ? $customer->getEmail() : null;
            $customerName = method_exists($customer, 'getName') ? $customer->getName() : null;

            // Create claims for JWT
            $claims = [
                new Subject((string)$customerId),
                new IssuedAt($currentDateTime),
                new ExpirationTime($expirationDateTime),
                new JwtId(bin2hex(random_bytes(16))),
                new Issuer($this->urlBuilder->getBaseUrl()),
                new Audience([$this->config->getTargetDomain()])
            ];

            // Add custom claims if data is available
            if ($customerEmail !== null) {
                $claims[] = new PrivateClaim('email', $customerEmail);
            }

            if ($customerName !== null) {
                $claims[] = new PrivateClaim('name', $customerName);
            }

            // Create the payload with claims
            $payload = new ClaimsPayload($claims);

            // Create header parameters for JWS
            $headerParams = [
                new \Magento\Framework\Jwt\Header\Algorithm('HS256'),
                new \Magento\Framework\Jwt\Header\PublicHeaderParameter('typ', null, 'JWT')
            ];

            // Create the JWT header
            $header = new JwsHeader($headerParams);

            // Create the JWT object
            $jwt = new Jws([$header], $payload, null);

            // Create the encryption settings with the shared secret key
            // Using symmetric key (HMAC) for HS256 algorithm
            // Get the native Magento encryption key for more security
            // This uses Magento's built-in encryption key from app/etc/env.php (crypt/key)
            $magentoKey = $this->encryptor->exportKeys();

            // Clean up the key (remove any newlines which represent multiple keys)
            $mainKey = explode("\n", $magentoKey)[0]; // Use the latest key

            // Ensure the key is at least 32 bytes (256 bits) for HS256
            if (strlen($mainKey) < 32) {
                // Hash the key if it's too short to meet the 32-byte requirement
                $mainKey = hash('sha256', $mainKey, true);
                $this->logger->debug('Magento encryption key was too short, using SHA-256 hash of it');
            }

            // The 'k' parameter must be base64url-encoded (RFC 7515)
            $encodedKey = rtrim(strtr(base64_encode($mainKey), '+/', '-_'), '=');

            $jwk = new Jwk('oct', [
                'k' => $encodedKey,
                'alg' => 'HS256'
            ]);
            $encryptionSettings = new JwsSignatureJwks($jwk);

            $this->logger->debug('Creating JWT token');

            // Create the JWT token using the JWT object and encryption settings
            $jwtToken = $this->jwtManager->create($jwt, $encryptionSettings);

            $this->logger->debug('JWT token created successfully.');
            return $jwtToken;
        } catch (\Exception $e) {
            $this->logger->error('Error generating JWT token: ' . $e->getMessage(), ['exception' => $e]);
            throw new LocalizedException(__('Failed to generate authentication token.'));
        }
    }
}
