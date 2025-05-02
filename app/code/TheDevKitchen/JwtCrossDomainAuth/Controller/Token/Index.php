<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Token generation controller for cross-domain authentication
 * Handles token generation requests from the frontend store switcher
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Token;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use TheDevKitchen\JwtCrossDomainAuth\Helper\LoggerHelper;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Queue\AuthEventPublisher;

/**
 * Controller for generating JWT tokens
 * Provides API endpoint for obtaining authentication tokens
 */
class Index implements HttpGetActionInterface
{
    /**
     * JSON response factory
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Module configuration
     * @var Config
     */
    private $config;

    /**
     * Token service for JWT operations
     * @var TokenServiceInterface
     */
    private $tokenService;

    /**
     * Customer session handler
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * Logger helper
     * @var LoggerHelper
     */
    private $loggerHelper;

    /**
     * Authentication event publisher
     * @var AuthEventPublisher
     */
    private $authEventPublisher;

    /**
     * Request
     * @var RequestInterface
     */
    private $request;

    /**
     * Constructor
     * 
     * @param JsonFactory $resultJsonFactory JSON response factory
     * @param Config $config Module configuration
     * @param TokenServiceInterface $tokenService JWT token service
     * @param CustomerSession $customerSession Customer session handler
     * @param LoggerHelper $loggerHelper Logger helper
     * @param AuthEventPublisher $authEventPublisher Authentication event publisher
     * @param RequestInterface $request HTTP request
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Config $config,
        TokenServiceInterface $tokenService,
        CustomerSession $customerSession,
        LoggerHelper $loggerHelper,
        AuthEventPublisher $authEventPublisher,
        RequestInterface $request
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->tokenService = $tokenService;
        $this->customerSession = $customerSession;
        $this->loggerHelper = $loggerHelper;
        $this->authEventPublisher = $authEventPublisher;
        $this->request = $request;
    }

    /**
     * Generate and return a JWT token for cross-domain authentication
     * Checks authentication status and generates token for valid customers
     *
     * Process flow:
     * 1. Verify module is enabled
     * 2. Check customer is logged in
     * 3. Generate token with customer data
     * 4. Return token in JSON response
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Verify module status
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Cross-domain authentication is disabled.'));
            }

            // Verify customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('You must be logged in to switch stores.'));
            }

            // Get current customer data
            $customer = $this->customerSession->getCustomer();

            // Prepare token payload with customer information
            $payload = [
                'sub' => (string)$customer->getId(),
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
                'iss' => 'magento2',
                'iat' => time(),
                'jti' => bin2hex(random_bytes(16))
            ];

            // Generate token using TokenService
            $token = $this->tokenService->generateToken($payload);

            // Track token generation via queue
            $this->publishTokenGenerationEvent($customer->getId(), $customer->getEmail(), $payload['jti']);

            return $result->setData(['success' => true, 'token' => $token]);

        } catch (LocalizedException $e) {
            $this->loggerHelper->log('JWT token generation failed: ' . $e->getMessage(), [], 'warning');
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->loggerHelper->log('Error generating JWT token: ' . $e->getMessage(), ['exception' => $e], 'error');
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while generating the authentication token.')
            ]);
        }
    }

    /**
     * Publish token generation event to the queue
     *
     * @param int $customerId Customer ID for which token was generated
     * @param string $customerEmail Customer email for audit trail
     * @param string $tokenId Unique token identifier
     * @return void
     */
    private function publishTokenGenerationEvent($customerId, string $customerEmail, string $tokenId): void
    {
        $targetDomain = $this->request->getParam('target_domain', null);
        
        $eventData = [
            'event_type' => 'auth.token.generated',
            'source_domain' => $this->config->getTargetDomain(),
            'target_domain' => $targetDomain,
            'user_info' => [
                'customer_id' => $customerId,
                'email' => $customerEmail,
                'is_logged_in' => true
            ],
            'request_metadata' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
            ],
            'security_metadata' => [
                'token_id' => $tokenId,
                'token_exp' => time() + $this->config->getJwtExpiration()
            ]
        ];

        // Log locally for immediate visibility (without exposing token)
        $this->loggerHelper->log(
            'JWT token generated for cross-domain authentication',
            [
                'customer_id' => $customerId,
                'token_id' => $tokenId
            ]
        );

        // Publish to queue for asynchronous processing
        $this->authEventPublisher->publish($eventData);
    }
}
