<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Integration\Controller\Token;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 */
class IndexTest extends AbstractController
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerSession = $this->_objectManager->get(Session::class);
        $this->customerRepository = $this->_objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        
        // Ensure customer session is clean
        $this->customerSession->logout();
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 1
     */
    public function testGenerateTokenForLoggedInCustomer()
    {
        // Get customer from fixture
        $customer = $this->customerRepository->get('customer@example.com');
        
        // Login customer
        $this->customerSession->setCustomerDataAsLoggedIn($customer);
        $this->assertTrue($this->customerSession->isLoggedIn());

        // Dispatch the token generation request
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('jwt/token');

        // Verify response format
        $this->assertJson($this->getResponse()->getBody());
        $responseData = json_decode($this->getResponse()->getBody(), true);
        
        // Check response structure
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertTrue($responseData['success']);
        
        // Check token format (should be a string with 3 parts separated by periods)
        $token = $responseData['token'];
        $this->assertIsString($token);
        $tokenParts = explode('.', $token);
        $this->assertCount(3, $tokenParts);
    }

    /**
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 1
     */
    public function testGenerateTokenFailsForGuestUser()
    {
        // Ensure we are not logged in
        $this->customerSession->logout();
        $this->assertFalse($this->customerSession->isLoggedIn());

        // Dispatch the token generation request
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('jwt/token');

        // Verify response format
        $this->assertJson($this->getResponse()->getBody());
        $responseData = json_decode($this->getResponse()->getBody(), true);
        
        // Check response structure
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('You must be logged in to switch stores.', $responseData['message']);
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 0
     */
    public function testGenerateTokenFailsWhenModuleDisabled()
    {
        // Get customer from fixture
        $customer = $this->customerRepository->get('customer@example.com');
        
        // Login customer
        $this->customerSession->setCustomerDataAsLoggedIn($customer);
        $this->assertTrue($this->customerSession->isLoggedIn());

        // Dispatch the token generation request
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('jwt/token');

        // Verify response format
        $this->assertJson($this->getResponse()->getBody());
        $responseData = json_decode($this->getResponse()->getBody(), true);
        
        // Check response structure
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Cross-domain authentication is disabled.', $responseData['message']);
    }
}