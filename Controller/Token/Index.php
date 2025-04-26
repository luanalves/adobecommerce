<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Token;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
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
     * Constructor
     *
     * @param JsonFactory $resultJsonFactory
     * @param Config $config
     * @param TokenGenerator $tokenGenerator
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Config $config,
        TokenGenerator $tokenGenerator
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->tokenGenerator = $tokenGenerator;
    }

    /**
     * Generate and return a JWT token for cross-domain authentication
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        if (!$this->config->isEnabled()) {
            return $result->setData(['success' => false, 'message' => 'Module is disabled']);
        }
        
        try {
            $token = $this->tokenGenerator->generateToken();
            return $result->setData(['success' => true, 'token' => $token]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}