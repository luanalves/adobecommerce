<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Crossdomain;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

/**
 * Controller for generating JWT tokens for cross-domain authentication
 */
class GenerateToken implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TokenServiceInterface
     */
    private $tokenService;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param DateTime $dateTime
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     * @param TokenServiceInterface $tokenService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        Config $config,
        DateTime $dateTime,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        TokenServiceInterface $tokenService
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->tokenService = $tokenService;
    }

    /**
     * Generate a JWT token for cross-domain authentication
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            // Check if module is enabled
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Cross-domain authentication is disabled.'));
            }

            // Check if customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('You must be logged in to generate a token.'));
            }

            // Get the customer
            $customer = $this->customerSession->getCustomer();

            // Prepare payload for token
            $currentTime = $this->dateTime->timestamp();
            $payload = [
                'sub' => (string)$customer->getId(),
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
                'iss' => $this->urlBuilder->getBaseUrl(),
                'iat' => $currentTime,
                'jti' => bin2hex(random_bytes(16)),
                'aud' => [$this->config->getTargetDomain()]
            ];

            // Generate token using TokenService
            $jwt = $this->tokenService->generateToken($payload);

            // Calculate expiration time for logging (based on config)
            $expirationTime = $currentTime + $this->config->getJwtExpiration();

            // Log successful token generation (without exposing the token)
            $this->logger->info('JWT token generated for customer', [
                'customer_id' => $customer->getId(),
                'expiration' => date('Y-m-d H:i:s', $expirationTime)
            ]);

            // Return success response with token
            return $resultJson->setData([
                'success' => true,
                'token' => $jwt
            ]);

        } catch (LocalizedException $e) {
            $this->logger->notice('JWT token generation failed: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error generating JWT token: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while generating the authentication token.')
            ]);
        }
    }
}
