<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

class Switcher extends Template
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        Config $config,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Get all websites for cross-domain switching
     *
     * @return array
     */
    public function getWebsites(): array
    {
        $websites = [];
        
        try {
            // Get all websites
            foreach ($this->storeManager->getWebsites() as $website) {
                // Skip current website
                if ($website->getId() == $this->storeManager->getStore()->getWebsiteId()) {
                    continue;
                }

                $websiteInfo = [
                    'id' => $website->getId(),
                    'name' => $website->getName(),
                    'code' => $website->getCode(),
                    'stores' => []
                ];

                // Get all stores for this website
                foreach ($website->getStores() as $store) {
                    if (!$store->isActive()) {
                        continue;
                    }

                    // Get domain for this store
                    $storeDomain = $this->getStoreDomain($store);
                    if (!$storeDomain) {
                        continue;
                    }

                    $websiteInfo['stores'][] = [
                        'id' => $store->getId(),
                        'name' => $store->getName(),
                        'code' => $store->getCode(),
                        'domain' => $storeDomain
                    ];
                }

                // Only add websites that have active stores
                if (!empty($websiteInfo['stores'])) {
                    $websites[] = $websiteInfo;
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error('Error fetching websites for switcher: ' . $e->getMessage());
        }

        return $websites;
    }

    /**
     * Get domain name for a store
     *
     * @param \Magento\Store\Model\Store $store
     * @return string|null
     */
    private function getStoreDomain($store): ?string
    {
        try {
            $baseUrl = $store->getBaseUrl();
            $urlParts = parse_url($baseUrl);
            
            if (isset($urlParts['scheme']) && isset($urlParts['host'])) {
                return $urlParts['scheme'] . '://' . $urlParts['host'];
            }
        } catch (\Exception $e) {
            $this->_logger->error('Error parsing store URL: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }
    
    /**
     * Check if cross-domain auth is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }
}