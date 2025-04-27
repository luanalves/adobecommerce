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
                $payload = $this->jwtManager->parse($token);
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
        // If it's a Claims Payload Interface (standard Magento JWT implementation)
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

        $this->logger->error('Unknown JWT payload format', ['type' => get_class($payload)]);
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
