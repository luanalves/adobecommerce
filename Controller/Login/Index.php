<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Login;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use Psr\Log\LoggerInterface;

class Index implements HttpGetActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param Config $config
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSession $customerSession
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        Config $config,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Execute login action based on JWT token
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        
        // Check if module is enabled
        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Cross-domain authentication is disabled.'));
            return $redirect->setPath('/');
        }
        
        $token = $this->request->getParam('token');
        if (!$token) {
            $this->messageManager->addErrorMessage(__('Invalid or missing authentication token.'));
            return $redirect->setPath('/');
        }
        
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                throw new \Exception(__('Invalid token format.'));
            }

            // Decode header and payload
            $header = json_decode($this->base64UrlDecode($tokenParts[0]), true);
            $payload = json_decode($this->base64UrlDecode($tokenParts[1]), true);

            // Verify token signature
            $signature = $tokenParts[2];
            $calculatedSignature = $this->generateSignature($tokenParts[0], $tokenParts[1], $this->config->getJwtSecret());
            if ($signature !== $calculatedSignature) {
                throw new \Exception(__('Invalid token signature.'));
            }

            // Verify token is not expired
            if (!isset($payload['exp']) || $this->dateTime->timestamp() > $payload['exp']) {
                throw new \Exception(__('Token has expired.'));
            }

            // If token contains customer data, log them in
            if (isset($payload['sub']) && isset($payload['email'])) {
                try {
                    $customer = $this->customerRepository->getById($payload['sub']);
                    
                    // Verify email matches
                    if ($customer->getEmail() !== $payload['email']) {
                        throw new \Exception(__('Customer email mismatch.'));
                    }
                    
                    // Log in the customer
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);
                    $this->messageManager->addSuccessMessage(__('You have been successfully logged in.'));
                    
                    // Log JWT authentication event asynchronously
                    // This would typically publish to a message queue for async processing
                    $this->logger->info('Cross-domain JWT authentication successful', [
                        'customer_id' => $payload['sub'],
                        'email' => $payload['email']
                    ]);
                    
                } catch (\Exception $e) {
                    $this->logger->error('Error during JWT authentication: ' . $e->getMessage());
                    throw new \Exception(__('Customer authentication failed.'));
                }
            }
            
            return $redirect->setPath('/');
            
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error('JWT authentication error: ' . $e->getMessage());
            return $redirect->setPath('/');
        }
    }

    /**
     * Base64 URL decode a string
     *
     * @param string $data
     * @return string
     */
    private function base64UrlDecode($data)
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        $base64 = str_pad($base64, strlen($base64) + (4 - strlen($base64) % 4) % 4, '=');
        return base64_decode($base64);
    }

    /**
     * Generate HMAC-SHA256 signature for JWT token verification
     *
     * @param string $header
     * @param string $payload
     * @param string $secret
     * @return string
     */
    private function generateSignature($header, $payload, $secret)
    {
        $signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    }
}
