<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Crossdomain;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\Jwt\HeaderParameterInterface;
use Magento\Framework\Jwt\ClaimInterface;
use Magento\Framework\Jwt\Jwe\JweHeader;
use Magento\Framework\Jwt\Payload\ClaimsPayloadInterface;
use Magento\Framework\Jwt\Payload\ClaimsFactory;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

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
     * @var JwtManagerInterface
     */
    private $jwtManager;

    /**
     * @var ClaimsFactory
     */
    private $claimsFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param DateTime $dateTime
     * @param UrlInterface $urlBuilder
     * @param JwtManagerInterface $jwtManager
     * @param ClaimsFactory $claimsFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        Config $config,
        DateTime $dateTime,
        UrlInterface $urlBuilder,
        JwtManagerInterface $jwtManager,
        ClaimsFactory $claimsFactory,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->urlBuilder = $urlBuilder;
        $this->jwtManager = $jwtManager;
        $this->claimsFactory = $claimsFactory;
        $this->logger = $logger;
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
            
            // Get current timestamp for JWT expiration
            $currentTime = $this->dateTime->timestamp();
            $expirationTime = $currentTime + 300; // Token valid for 5 minutes
            
            // Create claims for JWT payload
            $claims = [
                // Core JWT claims
                'sub' => (string)$customer->getId(), // Subject (customer ID)
                'iat' => $currentTime,               // Issued at
                'exp' => $expirationTime,            // Expiration
                'jti' => bin2hex(random_bytes(16)),  // JWT ID (unique identifier)
                'iss' => $this->urlBuilder->getBaseUrl(),       // Issuer (current domain)
                'aud' => $this->config->getTargetDomain(),      // Audience (target domain)
                
                // Custom claims with customer data
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
            ];
            
            // Create JWT token
            $claimsPayload = $this->claimsFactory->create($claims);
            $header = new JweHeader(
                'RS256',
                [
                    HeaderParameterInterface::PARAM_TYPE => 'JWT',
                    HeaderParameterInterface::PARAM_ALGORITHM => 'RS256',
                ]
            );
            
            $jwt = $this->jwtManager->create($claimsPayload, $header);
            
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