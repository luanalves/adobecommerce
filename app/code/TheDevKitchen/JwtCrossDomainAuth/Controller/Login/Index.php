<?php
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
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

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
     * @var TokenServiceInterface
     */
    private $tokenService;

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
     * @param TokenServiceInterface $tokenService
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
        TokenServiceInterface $tokenService,
        Config $config,
        LoggerInterface $logger,
        UrlInterface $urlBuilder
    ) {
        $this->request = $request;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->tokenService = $tokenService;
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

            $this->logger->debug('Processing JWT token: ' . substr($token, 0, 20) . '...');

            // Validate JWT token using TokenService
            try {
                $claims = $this->tokenService->validateToken($token);
                $this->logger->debug('JWT claims extracted: ' . json_encode(array_keys($claims)));
            } catch (\Exception $e) {
                $this->logger->warning('JWT validation failed: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid authentication token.'));
            }

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
