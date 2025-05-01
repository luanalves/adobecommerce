<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Login controller for cross-domain authentication
 * Handles the authentication process when users switch between domains
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Login;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use TheDevKitchen\JwtCrossDomainAuth\Helper\LoggerHelper;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

/**
 * Controller for validating JWT tokens and authenticating customers
 * Handles the receiving end of cross-domain authentication process
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
     * @var TokenServiceInterface
     */
    private $tokenService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerHelper
     */
    private $loggerHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Constructor
     * 
     * @param RequestInterface $request HTTP request handler
     * @param RedirectFactory $resultRedirectFactory Redirect response factory
     * @param MessageManagerInterface $messageManager User message manager
     * @param CustomerSession $customerSession Customer session handler
     * @param CustomerRepositoryInterface $customerRepository Customer data access
     * @param TokenServiceInterface $tokenService JWT token service
     * @param Config $config Module configuration
     * @param LoggerHelper $loggerHelper Logger helper service
     * @param UrlInterface $urlBuilder URL generation service
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $resultRedirectFactory,
        MessageManagerInterface $messageManager,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        TokenServiceInterface $tokenService,
        Config $config,
        LoggerHelper $loggerHelper,
        UrlInterface $urlBuilder
    ) {
        $this->request = $request;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->tokenService = $tokenService;
        $this->config = $config;
        $this->loggerHelper = $loggerHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Execute the cross-domain authentication process
     * Validates JWT token and logs in the customer if token is valid
     *
     * Process flow:
     * 1. Verify module is enabled
     * 2. Extract and validate JWT token
     * 3. Verify customer exists and matches token claims
     * 4. Log customer in and create new session
     * 5. Log authentication event for security tracking
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Verify module status
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Cross-domain authentication is disabled.'));
            }

            // Extract JWT token from request
            $token = $this->request->getParam('token');
            if (empty($token)) {
                throw new LocalizedException(__('Authentication token is missing.'));
            }

            $this->loggerHelper->log('Processing JWT token: ' . substr($token, 0, 20) . '...', [], 'debug');

            // Validate token and extract claims
            try {
                $claims = $this->tokenService->validateToken($token);
                $this->loggerHelper->log('JWT claims extracted: ' . json_encode(array_keys($claims)), [], 'debug');
            } catch (\Exception $e) {
                $this->loggerHelper->log('JWT validation failed: ' . $e->getMessage(), [], 'warning');
                throw new LocalizedException(__('Invalid authentication token.'));
            }

            // Extract and verify customer information
            $customerId = $claims['sub'] ?? null;
            $customerEmail = $claims['email'] ?? null;

            if (!$customerId) {
                throw new LocalizedException(__('Customer ID missing in token.'));
            }

            // Load and verify customer
            try {
                $customer = $this->customerRepository->getById((int)$customerId);
                $this->loggerHelper->log('Customer found: ' . $customerId, [], 'debug');

                // Verify email match if provided
                if ($customerEmail && $customer->getEmail() !== $customerEmail) {
                    $this->loggerHelper->log('Customer email mismatch', [], 'warning');
                    throw new LocalizedException(__('Customer verification failed.'));
                }

                // Create customer session
                $this->customerSession->setCustomerDataAsLoggedIn($customer);
                $this->messageManager->addSuccessMessage(__('You have been automatically logged in.'));

                // Log successful authentication
                $this->logAuthenticationEvent($customerId, $customer->getEmail(), $claims['iss'] ?? null);

                // Redirect to homepage after successful login
                $this->loggerHelper->log('Login successful, redirecting to homepage', [], 'debug');
                return $resultRedirect->setPath('/');

            } catch (NoSuchEntityException $e) {
                $this->loggerHelper->log('Customer not found: ' . $e->getMessage(), [], 'warning');
                throw new LocalizedException(__('We could not find your customer account. Please try logging in manually.'));
            }

        } catch (LocalizedException $e) {
            $this->loggerHelper->log('Login failed: ' . $e->getMessage(), [], 'notice');
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->loggerHelper->log('Error during cross-domain authentication: ' . $e->getMessage(), ['exception' => $e], 'error');
            $this->messageManager->addErrorMessage(__('An error occurred during authentication. Please try logging in manually.'));
        }

        // Redirect to login page on any error
        return $resultRedirect->setPath('customer/account/login');
    }

    /**
     * Log authentication event details for security tracking
     * Records successful authentication attempts with relevant context
     *
     * @param string $customerId Customer ID that was authenticated
     * @param string $customerEmail Customer email for audit trail
     * @param string|null $sourceDomain Origin domain of authentication request
     * @return void
     */
    private function logAuthenticationEvent(string $customerId, string $customerEmail, ?string $sourceDomain): void
    {
        $this->loggerHelper->log(
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
