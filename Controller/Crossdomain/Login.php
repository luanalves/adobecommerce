<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Crossdomain;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * Controller for validating JWT tokens for cross-domain authentication and logging in customers
 */
class Login implements HttpGetActionInterface
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
     * @param RequestInterface $request
     * @param RedirectFactory $resultRedirectFactory
     * @param MessageManagerInterface $messageManager
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param JwtManagerInterface $jwtManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param UrlInterface $urlBuilder
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
        UrlInterface $urlBuilder
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

            // Validate JWT token
            try {
                // Using read() instead of parse() and providing empty acceptable encryption array
                // This will validate the token without specific encryption settings
                $payload = $this->jwtManager->read($token, []);
            } catch (\Exception $e) {
                $this->logger->warning('JWT parsing failed: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid authentication token.'));
            }

            // Extract claims from the payload based on payload type
            $claims = $this->extractClaims($payload);

            // Get customer ID and email from claims
            $customerId = $claims['sub'] ?? null;
            $customerEmail = $claims['email'] ?? null;

            if (!$customerId || !$customerEmail) {
                throw new LocalizedException(__('Invalid customer information in token.'));
            }

            // Load customer by ID
            $customer = $this->customerRepository->getById((int)$customerId);

            // Verify that the customer email matches
            if ($customer->getEmail() !== $customerEmail) {
                throw new LocalizedException(__('Customer verification failed.'));
            }

            // Log the customer in
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->messageManager->addSuccessMessage(__('You have been automatically logged in.'));

            // Log successful authentication
            $this->logAuthenticationEvent($customerId, $customerEmail, $claims['iss'] ?? null);

            // Redirect to home page
            return $resultRedirect->setPath('/');

        } catch (NoSuchEntityException $e) {
            $this->logger->warning('Customer not found: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('We could not find your customer account. Please try logging in manually.'));
        } catch (LocalizedException $e) {
            $this->logger->notice('Login failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error during cross-domain authentication: ' . $e->getMessage());
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
                // For Jws class, try to access the claims through the header and payload methods
                if (method_exists($payload, 'getHeader') && method_exists($payload, 'getPayload')) {
                    $this->logger->debug('Using getHeader/getPayload methods for Jws');
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

                    // If payload is an object with getClaims method
                    if (is_object($jwtPayload) && method_exists($jwtPayload, 'getClaims')) {
                        $claimsArray = [];
                        foreach ($jwtPayload->getClaims() as $claim) {
                            $claimsArray[$claim->getName()] = $claim->getValue();
                        }
                        return $claimsArray;
                    }
                }

                // Try using reflection to access protected properties
                $reflectionClass = new \ReflectionClass($payload);
                foreach (['payload', 'claims', 'data'] as $propertyName) {
                    if ($reflectionClass->hasProperty($propertyName)) {
                        $property = $reflectionClass->getProperty($propertyName);
                        $property->setAccessible(true);
                        $value = $property->getValue($payload);

                        if (is_array($value)) {
                            return $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }
                        } elseif (is_object($value) && method_exists($value, 'getClaims')) {
                            $claimsArray = [];
                            foreach ($value->getClaims() as $claim) {
                                $claimsArray[$claim->getName()] = $claim->getValue();
                            }
                            return $claimsArray;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error accessing Jws properties: ' . $e->getMessage());
            }
        }

        // If it's a JwtInterface
        if ($payload instanceof \Magento\Framework\Jwt\JwtInterface) {
            try {
                // Try accessing the content through various methods
                if (method_exists($payload, 'getContent')) {
                    $jwtContent = $payload->getContent();
                    $this->logger->debug('JWT content type: ' . (is_object($jwtContent) ? get_class($jwtContent) : gettype($jwtContent)));

                    // Handle different payload types
                    if ($jwtContent instanceof \Magento\Framework\Jwt\Payload\ClaimsPayloadInterface) {
                        $claimsArray = [];
                        foreach ($jwtContent->getClaims() as $claim) {
                            $claimsArray[$claim->getName()] = $claim->getValue();
                        }
                        return $claimsArray;
                    } elseif (is_string($jwtContent)) {
                        $decodedContent = json_decode($jwtContent, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                            return $decodedContent;
                        }
                    } elseif (is_array($jwtContent)) {
                        return $jwtContent;
                    }
                }

                // Try to get protected properties via reflection
                $reflectionClass = new \ReflectionClass($payload);
                foreach (['content', 'claims', 'payload'] as $propertyName) {
                    if ($reflectionClass->hasProperty($propertyName)) {
                        $property = $reflectionClass->getProperty($propertyName);
                        $property->setAccessible(true);
                        $value = $property->getValue($payload);

                        if (is_array($value)) {
                            return $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error accessing JwtInterface properties: ' . $e->getMessage());
            }
        }

        // If it's a Claims Payload Interface directly
        if (interface_exists('\Magento\Framework\Jwt\Payload\ClaimsPayloadInterface') &&
            $payload instanceof \Magento\Framework\Jwt\Payload\ClaimsPayloadInterface) {

            $claimsArray = [];
            foreach ($payload->getClaims() as $claim) {
                $claimsArray[$claim->getName()] = $claim->getValue();
            }
            return $claimsArray;
        }

        // If it's a JSON string
        if (is_string($payload)) {
            $decodedPayload = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
                return $decodedPayload;
            }
        }

        // If it's already an array
        if (is_array($payload)) {
            return $payload;
        }

        // Last attempt - try to get data through reflection for any other object type
        if (is_object($payload)) {
            $this->logger->debug('Attempting to extract claims via reflection from: ' . get_class($payload));
            try {
                $reflectionObject = new \ReflectionObject($payload);

                // Try to find claims or content properties/methods
                foreach (['claims', 'content', 'payload', 'data'] as $possibleProperty) {
                    // Check for property
                    if ($reflectionObject->hasProperty($possibleProperty)) {
                        $property = $reflectionObject->getProperty($possibleProperty);
                        $property->setAccessible(true);
                        $value = $property->getValue($payload);

                        if (is_array($value)) {
                            return $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }
                        }
                    }

                    // Check for getter method
                    $getterMethod = 'get' . ucfirst($possibleProperty);
                    if ($reflectionObject->hasMethod($getterMethod)) {
                        $method = $reflectionObject->getMethod($getterMethod);
                        $method->setAccessible(true);
                        $value = $method->invoke($payload);

                        if (is_array($value)) {
                            return $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Reflection attempt failed: ' . $e->getMessage());
            }
        }

        // If we get here, we couldn't extract claims
        $this->logger->error('Unknown JWT payload format', [
            'type' => is_object($payload) ? get_class($payload) : gettype($payload),
            'payload' => is_scalar($payload) ? $payload : 'non-scalar'
        ]);
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
