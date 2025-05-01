<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TheDevKitchen\JwtCrossDomainAuth\Block\Switcher;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

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
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSessionMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->configMock = $this->createMock(Config::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->block = $this->objectManager->getObject(
            Switcher::class,
            [
                'context' => $this->contextMock,
                'storeManager' => $this->storeManagerMock,
                'customerSession' => $this->customerSessionMock,
                'config' => $this->configMock
            ]
        );
    }

    public function testIsCustomerLoggedInReturnsTrueWhenLoggedIn(): void
    {
        $this->customerSessionMock->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(true);

        $this->assertTrue($this->block->isCustomerLoggedIn());
    }

    public function testIsCustomerLoggedInReturnsFalseWhenNotLoggedIn(): void
    {
        $this->customerSessionMock->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(false);

        $this->assertFalse($this->block->isCustomerLoggedIn());
    }

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->assertTrue($this->block->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->assertFalse($this->block->isEnabled());
    }

    public function testGetWebsitesReturnsEmptyArrayWhenNoOtherWebsites(): void
    {
        $currentWebsite = $this->createMock(Website::class);
        $currentWebsite->method('getId')->willReturn(1);

        $currentStore = $this->createMock(Store::class);
        $currentStore->method('getWebsiteId')->willReturn(1);

        $this->storeManagerMock->method('getWebsites')
            ->willReturn([$currentWebsite]);
        $this->storeManagerMock->method('getStore')
            ->willReturn($currentStore);

        $this->assertEquals([], $this->block->getWebsites());
    }

    public function testGetWebsitesReturnsFormattedWebsiteData(): void
    {
        $currentWebsite = $this->createMock(Website::class);
        $currentWebsite->method('getId')->willReturn(1);

        $otherWebsite = $this->createMock(Website::class);
        $otherWebsite->method('getId')->willReturn(2);
        $otherWebsite->method('getName')->willReturn('Test Website');
        $otherWebsite->method('getCode')->willReturn('test_website');

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getName')->willReturn('Test Store');
        $store->method('getCode')->willReturn('test_store');
        $store->method('isActive')->willReturn(true);
        $store->method('getBaseUrl')->willReturn('https://test-store.com/');

        $currentStore = $this->createMock(Store::class);
        $currentStore->method('getWebsiteId')->willReturn(1);

        $otherWebsite->method('getStores')
            ->willReturn([$store]);

        $this->storeManagerMock->method('getWebsites')
            ->willReturn([$currentWebsite, $otherWebsite]);
        $this->storeManagerMock->method('getStore')
            ->willReturn($currentStore);

        $expectedWebsites = [
            [
                'id' => 2,
                'name' => 'Test Website',
                'code' => 'test_website',
                'stores' => [
                    [
                        'id' => 2,
                        'name' => 'Test Store',
                        'code' => 'test_store',
                        'domain' => 'https://test-store.com'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedWebsites, $this->block->getWebsites());
    }

    public function testGetWebsitesSkipsInactiveStores(): void
    {
        $currentWebsite = $this->createMock(Website::class);
        $currentWebsite->method('getId')->willReturn(1);

        $otherWebsite = $this->createMock(Website::class);
        $otherWebsite->method('getId')->willReturn(2);
        $otherWebsite->method('getName')->willReturn('Test Website');
        $otherWebsite->method('getCode')->willReturn('test_website');

        $inactiveStore = $this->createMock(Store::class);
        $inactiveStore->method('isActive')->willReturn(false);

        $currentStore = $this->createMock(Store::class);
        $currentStore->method('getWebsiteId')->willReturn(1);

        $otherWebsite->method('getStores')
            ->willReturn([$inactiveStore]);

        $this->storeManagerMock->method('getWebsites')
            ->willReturn([$currentWebsite, $otherWebsite]);
        $this->storeManagerMock->method('getStore')
            ->willReturn($currentStore);

        $this->assertEquals([], $this->block->getWebsites());
    }
}