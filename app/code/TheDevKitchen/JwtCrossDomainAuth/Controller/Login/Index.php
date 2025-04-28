<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Login;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Jwt\Jwk;
use Magento\Framework\Jwt\Jws\JwsSignatureJwks;
use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * Controller for validating JWT tokens for cross-domain authentication and logging in customers
 */
class Index implements HttpGetActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

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
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param RequestInterface $request
     * @param RedirectFactory $resultRedirectFactory
     * @param MessageManagerInterface $messageManager
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param JwtManagerInterface $jwtManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param UrlInterface $urlBuilder
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $resultRedirectFactory,
        MessageManagerInterface $messageManager,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        JwtManagerInterface $jwtManager,
        Config $config,
        LoggerInterface $logger,
        UrlInterface $urlBuilder,
        EncryptorInterface $encryptor
    ) {
        $this->request = $request;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->jwtManager = $jwtManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->encryptor = $encryptor;
    }

    /**
     * Authenticate customer using JWT token
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Check if module is enabled
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Cross-domain authentication is disabled.'));
            }

            // Get JWT token from request
            $token = $this->request->getParam('token');
            if (empty($token)) {
                throw new LocalizedException(__('Authentication token is missing.'));
            }

            $this->logger->debug('Processing JWT token: ' . substr($token, 0, 20) . '...');

            // Create the verification settings with the shared secret key
            // Using Magento's built-in encryption key from app/etc/env.php (crypt/key)
            $magentoKey = $this->encryptor->exportKeys();

            // Clean up the key (remove any newlines which represent multiple keys)
            $mainKey = explode("\n", $magentoKey)[0]; // Use the latest key

            // Ensure the key is at least 32 bytes for HS256
            if (strlen($mainKey) < 32) {
                $mainKey = hash('sha256', $mainKey, true);
                $this->logger->debug('Magento encryption key was too short, using SHA-256 hash of it');
            }

            // The 'k' parameter must be base64url-encoded (RFC 7515)
            $encodedKey = rtrim(strtr(base64_encode($mainKey), '+/', '-_'), '=');

            $jwk = new Jwk('oct', [
                'k' => $encodedKey,
                'alg' => 'HS256'
            ]);

            $verificationSettings = new JwsSignatureJwks($jwk);

            // Validate JWT token
            try {
                // Replace parse() with read() and pass the verification settings as an array
                $payload = $this->jwtManager->read($token, [$verificationSettings]);
                $this->logger->debug('JWT successfully read');
            } catch (\Exception $e) {
                $this->logger->warning('JWT parsing failed: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid authentication token.'));
            }

            // Extract claims from the payload based on payload type
            $claims = $this->extractClaims($payload);
            $this->logger->debug('JWT claims extracted: ' . json_encode(array_keys($claims)));

            // Get customer ID and email from claims
            $customerId = $claims['sub'] ?? null;
            $customerEmail = $claims['email'] ?? null;

            if (!$customerId) {
                throw new LocalizedException(__('Customer ID missing in token.'));
            }

            // Load customer by ID
            try {
                $customer = $this->customerRepository->getById((int)$customerId);
                $this->logger->debug('Customer found: ' . $customerId);

                // Verify that the customer email matches if available in token
                if ($customerEmail && $customer->getEmail() !== $customerEmail) {
                    $this->logger->warning('Customer email mismatch');
                    throw new LocalizedException(__('Customer verification failed.'));
                }

                // Log the customer in
                $this->customerSession->setCustomerDataAsLoggedIn($customer);
                $this->messageManager->addSuccessMessage(__('You have been automatically logged in.'));

                // Log successful authentication
                $this->logAuthenticationEvent($customerId, $customer->getEmail(), $claims['iss'] ?? null);

                // Redirect to home page
                $this->logger->debug('Login successful, redirecting to homepage');
                return $resultRedirect->setPath('/');

            } catch (NoSuchEntityException $e) {
                $this->logger->warning('Customer not found: ' . $e->getMessage());
                throw new LocalizedException(__('We could not find your customer account. Please try logging in manually.'));
            }

        } catch (LocalizedException $e) {
            $this->logger->notice('Login failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error during cross-domain authentication: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('An error occurred during authentication. Please try logging in manually.'));
        }

        // In case of any error, redirect to login page
        return $resultRedirect->setPath('customer/account/login');
    }

    /**
     * Extract claims from the JWT payload based on its type
     *
     * @param mixed $payload
     * @return array
     * @throws LocalizedException
     */
    private function extractClaims($payload): array
    {
        $this->logger->debug('Extracting claims from payload of type: ' . (is_object($payload) ? get_class($payload) : gettype($payload)));

        // Handle Magento\Framework\Jwt\Jws\Jws directly
        if ($payload instanceof \Magento\Framework\Jwt\Jws\Jws) {
            try {
                // For Jws class, try to access the claims through the getPayload method
                if (method_exists($payload, 'getPayload')) {
                    $this->logger->debug('Using getPayload method for Jws');
                    $jwtPayload = $payload->getPayload();

                    // If payload is a string, try to decode it
                    if (is_string($jwtPayload)) {
                        $decoded = json_decode($jwtPayload, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }
                    }

                    // If payload is already an array
                    if (is_array($jwtPayload)) {
                        return $jwtPayload;
                    }
                }

                // Try accessing the content directly
                if (method_exists($payload, 'getContent')) {
                    $this->logger->debug('Using getContent method for Jws');
                    $content = $payload->getContent();
                    if (is_array($content)) {
                        return $content;
                    }

                    // If content is a string, try to decode it
                    if (is_string($content)) {
                        $decoded = json_decode($content, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }
                    }
                }

                // Try using reflection to access protected properties if direct methods don't work
                $reflectionClass = new \ReflectionClass($payload);
                foreach (['payload', 'claims', 'data', 'content', 'protectedHeader'] as $propertyName) {
                    if ($reflectionClass->hasProperty($propertyName)) {
                        $property = $reflectionClass->getProperty($propertyName);
                        $property->setAccessible(true);
                        $value = $property->getValue($payload);

                        if (is_array($value)) {
                            $this->logger->debug('Found claims in property: ' . $propertyName);
                            return $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $this->logger->debug('Decoded JSON string from property: ' . $propertyName);
                                return $decoded;
                            }
                        } elseif (is_object($value)) {
                            // Try to extract data from the object
                            if (method_exists($value, 'getAll')) {
                                $allData = $value->getAll();
                                if (is_array($allData)) {
                                    $this->logger->debug('Extracted claims using getAll() from property: ' . $propertyName);
                                    return $allData;
                                }
                            }

                            // Try to convert object to array if possible
                            try {
                                $valueArray = (array)$value;
                                if (!empty($valueArray)) {
                                    $this->logger->debug('Converted object to array from property: ' . $propertyName);
                                    return $valueArray;
                                }
                            } catch (\Exception $e) {
                                $this->logger->debug('Failed to convert object to array: ' . $e->getMessage());
                            }
                        }
                    }
                }

                // Try to access methods that might contain the claims
                foreach (['getAllClaims', 'getHeader', 'getClaims', 'getBody'] as $methodName) {
                    if (method_exists($payload, $methodName)) {
                        $this->logger->debug('Trying method: ' . $methodName);
                        try {
                            $result = $payload->$methodName();
                            if (is_array($result)) {
                                return $result;
                            }

                            if (is_string($result)) {
                                $decoded = json_decode($result, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    return $decoded;
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logger->debug('Error calling method ' . $methodName . ': ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error accessing Jws properties: ' . $e->getMessage());
            }
        }

        // If it's a standard JWT interface (not Jws)
        if ($payload instanceof \Magento\Framework\Jwt\JwtInterface) {
            try {
                $claimsArray = [];

                // Try accessing the claims through standard JWT interfaces
                if (method_exists($payload, 'getClaims')) {
                    foreach ($payload->getClaims() as $claim) {
                        $claimsArray[$claim->getName()] = $claim->getValue();
                    }
                    return $claimsArray;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error accessing JWT claims: ' . $e->getMessage());
            }
        }

        // As a fallback, if the payload is directly an array, return it
        if (is_array($payload)) {
            return $payload;
        }

        // Attempt to handle JWK payload format (common in newer JWT implementations)
        if (is_object($payload)) {
            // For JWT payloads using different namespaces or implementations
            $possibleMethods = [
                'getJsonPayload', 'getBody', 'readPayload', 'getTokenPayload',
                'getClaimsAsArray', 'getContents', 'getParameters', 'decode'
            ];

            foreach ($possibleMethods as $method) {
                if (method_exists($payload, $method)) {
                    $this->logger->debug('Trying alternative method: ' . $method);
                    try {
                        $result = $payload->$method();

                        if (is_array($result)) {
                            return $result;
                        }

                        if (is_string($result)) {
                            $decoded = json_decode($result, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->debug('Method ' . $method . ' failed: ' . $e->getMessage());
                    }
                }
            }

            // Try to convert the entire object to array directly
            try {
                $arrayRepresentation = json_decode(json_encode($payload), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($arrayRepresentation)) {
                    $this->logger->debug('Converted entire object to array via JSON serialization');

                    // Look for common JWT claim keys in the array
                    $jwtClaimKeys = ['sub', 'iss', 'aud', 'exp', 'nbf', 'iat', 'jti', 'email'];
                    $foundClaims = array_intersect_key($arrayRepresentation, array_flip($jwtClaimKeys));

                    if (!empty($foundClaims)) {
                        return $arrayRepresentation;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug('JSON serialization failed: ' . $e->getMessage());
            }
        }

        // Try to directly parse the token if payload is a string
        if (is_string($payload)) {
            // Attempt to parse the raw JWT token
            $parts = explode('.', $payload);
            if (count($parts) === 3) { // Standard JWT has 3 parts: header.payload.signature
                try {
                    $payloadBase64 = $parts[1];
                    // Ensure proper base64url padding
                    $payloadBase64 = strtr($payloadBase64, '-_', '+/');
                    $payloadBase64 = str_pad($payloadBase64, strlen($payloadBase64) % 4, '=', STR_PAD_RIGHT);

                    $decodedPayload = base64_decode($payloadBase64, true);
                    if ($decodedPayload !== false) {
                        $jsonPayload = json_decode($decodedPayload, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonPayload)) {
                            $this->logger->debug('Successfully parsed raw JWT token string');
                            return $jsonPayload;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Failed to parse raw JWT: ' . $e->getMessage());
                }
            }
        }

        // DO NOT try to cast the object to string as it can cause the error
        // Instead, check if the object has a __toString method or can be serialized
        if (is_object($payload) && method_exists($payload, '__toString')) {
            try {
                $payloadStr = $payload->__toString();
                $decoded = json_decode($payloadStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Exception $e) {
                // Ignore conversion errors
                $this->logger->warning('Error in __toString conversion: ' . $e->getMessage());
            }
        }

        // Log the actual content of the payload for debugging
        if (is_object($payload)) {
            $this->logger->debug('Payload object class: ' . get_class($payload));
            $this->logger->debug('Payload object methods: ' . implode(', ', get_class_methods($payload)));
        } else {
            $this->logger->debug('Payload is not an object: ' . gettype($payload));
        }

        // If all else fails, throw an exception
        $this->logger->error('Unable to extract claims from JWT payload');
        throw new LocalizedException(__('Unable to process authentication token.'));
    }

    /**
     * Log the authentication event
     *
     * @param string $customerId
     * @param string $customerEmail
     * @param string|null $sourceDomain
     * @return void
     */
    private function logAuthenticationEvent(string $customerId, string $customerEmail, ?string $sourceDomain): void
    {
        $this->logger->info(
            'Cross-domain authentication successful',
            [
                'customer_id' => $customerId,
                'email' => $customerEmail,
                'timestamp' => time(),
                'success' => true,
                'source_domain' => $sourceDomain
            ]
        );
    }
}
