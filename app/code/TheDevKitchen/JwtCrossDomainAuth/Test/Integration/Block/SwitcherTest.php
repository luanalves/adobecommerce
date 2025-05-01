<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Integration\Block;

use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use TheDevKitchen\JwtCrossDomainAuth\Block\Switcher;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * @magentoAppArea frontend
 */
class SwitcherTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Switcher
     */
    private $block;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->customerSession = $this->objectManager->get(Session::class);
        $this->config = $this->objectManager->get(Config::class);

        $this->block = $this->objectManager->create(Switcher::class);
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store jwt_crossdomain_auth/general/enabled 1
     */
    public function testSwitcherVisibilityWithLoggedInCustomer(): void
    {
        // Log in the customer
        $this->customerSession->setCustomerAsLoggedIn(
            $this->objectManager->create(\Magento\Customer\Model\Customer::class)
                ->load(1)
        );

        // Get available websites
        $websites = $this->block->getWebsites();

        // Verify websites array is not empty
        $this->assertNotEmpty($websites);

        // Verify each website has required data
        foreach ($websites as $website) {
            $this->assertArrayHasKey('id', $website);
            $this->assertArrayHasKey('name', $website);
            $this->assertArrayHasKey('code', $website);
            $this->assertArrayHasKey('stores', $website);
            $this->assertNotEmpty($website['stores']);

            // Verify store data
            foreach ($website['stores'] as $store) {
                $this->assertArrayHasKey('id', $store);
                $this->assertArrayHasKey('name', $store);
                $this->assertArrayHasKey('code', $store);
                $this->assertArrayHasKey('domain', $store);
                $this->assertNotEmpty($store['domain']);
            }
        }

        // Verify the block is enabled and visible
        $this->assertTrue($this->block->isEnabled());
        $this->assertTrue($this->block->isCustomerLoggedIn());
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
     * @magentoConfigFixture current_store jwt_crossdomain_auth/general/enabled 1
     */
    public function testSwitcherNotVisibleForGuestCustomer(): void
    {
        // Ensure customer is logged out
        $this->customerSession->logout();

        // Get available websites
        $websites = $this->block->getWebsites();

        // Verify websites data is available
        $this->assertNotEmpty($websites);

        // But the block should not be visible because customer is not logged in
        $this->assertTrue($this->block->isEnabled());
        $this->assertFalse($this->block->isCustomerLoggedIn());
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store jwt_crossdomain_auth/general/enabled 0
     */
    public function testSwitcherNotVisibleWhenDisabled(): void
    {
        // Log in the customer
        $this->customerSession->setCustomerAsLoggedIn(
            $this->objectManager->create(\Magento\Customer\Model\Customer::class)
                ->load(1)
        );

        // Verify the block is not enabled even with logged in customer
        $this->assertFalse($this->block->isEnabled());
        $this->assertTrue($this->block->isCustomerLoggedIn());
    }
}