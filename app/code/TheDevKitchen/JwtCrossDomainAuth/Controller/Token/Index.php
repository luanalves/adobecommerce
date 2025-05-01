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
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

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
     * System logger
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     * 
     * @param JsonFactory $resultJsonFactory JSON response factory
     * @param Config $config Module configuration
     * @param TokenServiceInterface $tokenService JWT token service
     * @param CustomerSession $customerSession Customer session handler
     * @param LoggerInterface $logger System logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Config $config,
        TokenServiceInterface $tokenService,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->tokenService = $tokenService;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
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

            // Log success (without exposing token)
            $this->logger->info('JWT token generated for cross-domain authentication', [
                'customer_id' => $customer->getId()
            ]);

            return $result->setData(['success' => true, 'token' => $token]);

        } catch (LocalizedException $e) {
            $this->logger->notice('JWT token generation failed: ' . $e->getMessage());
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->error('Error generating JWT token: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while generating the authentication token.')
            ]);
        }
    }
}
