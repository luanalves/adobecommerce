<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Integration\Controller\Login;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\TestCase\AbstractController;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

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

    /**
     * @var TokenServiceInterface
     */
    private $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerSession = $this->_objectManager->get(Session::class);
        $this->customerRepository = $this->_objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $this->tokenService = $this->_objectManager->get(TokenServiceInterface::class);
        
        // Ensure customer session is clean
        $this->customerSession->logout();
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 1
     */
    public function testLoginWithValidToken()
    {
        // Get customer from fixture
        $customer = $this->customerRepository->get('customer@example.com');
        $customerId = $customer->getId();
        
        // Generate a valid token for the customer
        $payload = [
            'sub' => (string)$customerId,
            'email' => $customer->getEmail(),
            'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            'iss' => 'magento2',
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16))
        ];
        
        $token = $this->tokenService->generateToken($payload);
        
        // Ensure we are not logged in
        $this->customerSession->logout();
        $this->assertFalse($this->customerSession->isLoggedIn());

        // Dispatch the login request with token
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParam('token', $token);
        $this->dispatch('jwt/login');

        // Verify we are redirected to the homepage
        $this->assertRedirect($this->stringContains('/'));
        
        // Verify the success message
        $messages = $this->getMessages();
        $this->assertGreaterThanOrEqual(1, count($messages));
        $hasSuccessMessage = false;
        foreach ($messages as $message) {
            if ($message->getType() === MessageInterface::TYPE_SUCCESS && 
                strpos($message->getText(), 'You have been automatically logged in') !== false) {
                $hasSuccessMessage = true;
                break;
            }
        }
        $this->assertTrue($hasSuccessMessage, 'Success message not found');
        
        // Verify that we are logged in
        $this->assertTrue($this->customerSession->isLoggedIn());
        $this->assertEquals($customerId, $this->customerSession->getCustomerId());
    }

    /**
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 1
     */
    public function testLoginWithMissingToken()
    {
        // Ensure we are not logged in
        $this->customerSession->logout();
        $this->assertFalse($this->customerSession->isLoggedIn());

        // Dispatch the login request without a token
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('jwt/login');

        // Verify we are redirected to the login page
        $this->assertRedirect($this->stringContains('customer/account/login'));
        
        // Verify the error message
        $messages = $this->getMessages();
        $this->assertGreaterThanOrEqual(1, count($messages));
        $hasErrorMessage = false;
        foreach ($messages as $message) {
            if ($message->getType() === MessageInterface::TYPE_ERROR && 
                strpos($message->getText(), 'Authentication token is missing') !== false) {
                $hasErrorMessage = true;
                break;
            }
        }
        $this->assertTrue($hasErrorMessage, 'Error message not found');
        
        // Verify that we are still not logged in
        $this->assertFalse($this->customerSession->isLoggedIn());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 1
     */
    public function testLoginWithInvalidToken()
    {
        // Ensure we are not logged in
        $this->customerSession->logout();
        $this->assertFalse($this->customerSession->isLoggedIn());

        // Dispatch the login request with an invalid token
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParam('token', 'invalid.token.string');
        $this->dispatch('jwt/login');

        // Verify we are redirected to the login page
        $this->assertRedirect($this->stringContains('customer/account/login'));
        
        // Verify the error message
        $messages = $this->getMessages();
        $this->assertGreaterThanOrEqual(1, count($messages));
        $hasErrorMessage = false;
        foreach ($messages as $message) {
            if ($message->getType() === MessageInterface::TYPE_ERROR && 
                strpos($message->getText(), 'Invalid authentication token') !== false) {
                $hasErrorMessage = true;
                break;
            }
        }
        $this->assertTrue($hasErrorMessage, 'Error message not found');
        
        // Verify that we are still not logged in
        $this->assertFalse($this->customerSession->isLoggedIn());
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store thedevkitchen_jwt/general/enabled 0
     */
    public function testLoginFailsWhenModuleDisabled()
    {
        // Get customer from fixture
        $customer = $this->customerRepository->get('customer@example.com');
        
        // Generate a valid token for the customer
        $payload = [
            'sub' => (string)$customer->getId(),
            'email' => $customer->getEmail(),
            'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            'iss' => 'magento2',
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16))
        ];
        
        $token = $this->tokenService->generateToken($payload);
        
        // Ensure we are not logged in
        $this->customerSession->logout();
        $this->assertFalse($this->customerSession->isLoggedIn());

        // Dispatch the login request with token
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->getRequest()->setParam('token', $token);
        $this->dispatch('jwt/login');

        // Verify we are redirected to the login page
        $this->assertRedirect($this->stringContains('customer/account/login'));
        
        // Verify the error message
        $messages = $this->getMessages();
        $this->assertGreaterThanOrEqual(1, count($messages));
        $hasErrorMessage = false;
        foreach ($messages as $message) {
            if ($message->getType() === MessageInterface::TYPE_ERROR && 
                strpos($message->getText(), 'Cross-domain authentication is disabled') !== false) {
                $hasErrorMessage = true;
                break;
            }
        }
        $this->assertTrue($hasErrorMessage, 'Error message not found');
        
        // Verify that we are still not logged in
        $this->assertFalse($this->customerSession->isLoggedIn());
    }

    /**
     * Helper method to get messages from the session
     *
     * @return \Magento\Framework\Message\MessageInterface[]
     */
    private function getMessages()
    {
        $messages = $this->_objectManager->get(\Magento\Framework\Message\ManagerInterface::class)
            ->getMessages()->getItems();
        return $messages;
    }
}