<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Token;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Model\TokenGenerator;

class Index implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TokenGenerator
     */
    private $tokenGenerator;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param TokenGenerator $tokenGenerator
     * @param CustomerSession $customerSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Config $config,
        TokenGenerator $tokenGenerator,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->tokenGenerator = $tokenGenerator;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * Generate and return a JWT token for cross-domain authentication
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Check if module is enabled
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Cross-domain authentication is disabled.'));
            }

            // Check if customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('You must be logged in to switch stores.'));
            }

            // Get customer data
            $customer = $this->customerSession->getCustomer();

            // Generate token for the customer
            $token = $this->tokenGenerator->generateTokenForCustomer($customer);

            // Log success message (without exposing the token)
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
