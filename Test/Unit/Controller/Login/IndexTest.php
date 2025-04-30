<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Controller\Login;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Controller\Login\Index;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

class IndexTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var RedirectFactory|MockObject
     */
    private $redirectFactoryMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManagerMock;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSessionMock;

    /**
     * @var CustomerRepositoryInterface|MockObject
     */
    private $customerRepositoryMock;

    /**
     * @var TokenServiceInterface|MockObject
     */
    private $tokenServiceMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @var Redirect|MockObject
     */
    private $redirectMock;

    /**
     * @var CustomerInterface|MockObject
     */
    private $customerMock;

    /**
     * @var Index
     */
    private $controller;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepositoryInterface::class);
        $this->tokenServiceMock = $this->createMock(TokenServiceInterface::class);
        $this->configMock = $this->createMock(Config::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->customerMock = $this->createMock(CustomerInterface::class);

        $this->redirectFactoryMock->method('create')
            ->willReturn($this->redirectMock);

        $this->controller = $this->objectManager->getObject(Index::class, [
            'request' => $this->requestMock,
            'resultRedirectFactory' => $this->redirectFactoryMock,
            'messageManager' => $this->messageManagerMock,
            'customerSession' => $this->customerSessionMock,
            'customerRepository' => $this->customerRepositoryMock,
            'tokenService' => $this->tokenServiceMock,
            'config' => $this->configMock,
            'logger' => $this->loggerMock,
            'urlBuilder' => $this->urlBuilderMock
        ]);
    }

    public function testExecuteWithModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Cross-domain authentication is disabled.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteMissingToken()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn('');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Authentication token is missing.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteWithInvalidToken()
    {
        $token = 'invalid.jwt.token';

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);

        $this->tokenServiceMock->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willThrowException(new LocalizedException(__('Invalid authentication token.')));

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('JWT validation failed'));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Invalid authentication token.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteWithMissingCustomerId()
    {
        $token = 'valid.jwt.token';
        $claims = [
            'iss' => 'magento2',
            'iat' => time()
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);

        $this->tokenServiceMock->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($claims);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Customer ID missing in token.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteWithCustomerNotFound()
    {
        $token = 'valid.jwt.token';
        $customerId = 123;
        $claims = [
            'sub' => $customerId,
            'email' => 'customer@example.com',
            'iss' => 'magento2',
            'iat' => time()
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);

        $this->tokenServiceMock->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($claims);

        $this->customerRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($customerId)
            ->willThrowException(new NoSuchEntityException(__('Customer not found')));

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Customer not found'));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('We could not find your customer account. Please try logging in manually.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteWithEmailMismatch()
    {
        $token = 'valid.jwt.token';
        $customerId = 123;
        $tokenEmail = 'token@example.com';
        $customerEmail = 'customer@example.com';
        $claims = [
            'sub' => $customerId,
            'email' => $tokenEmail,
            'iss' => 'magento2',
            'iat' => time()
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);

        $this->tokenServiceMock->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($claims);

        $this->customerRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($customerId)
            ->willReturn($this->customerMock);

        $this->customerMock->expects($this->once())
            ->method('getEmail')
            ->willReturn($customerEmail);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with('Customer email mismatch');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Customer verification failed.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('customer/account/login')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteSuccess()
    {
        $token = 'valid.jwt.token';
        $customerId = 123;
        $customerEmail = 'customer@example.com';
        $claims = [
            'sub' => $customerId,
            'email' => $customerEmail,
            'iss' => 'magento2',
            'iat' => time()
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);

        $this->tokenServiceMock->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($claims);

        $this->customerRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($customerId)
            ->willReturn($this->customerMock);

        $this->customerMock->expects($this->once())
            ->method('getEmail')
            ->willReturn($customerEmail);

        $this->customerSessionMock->expects($this->once())
            ->method('setCustomerDataAsLoggedIn')
            ->with($this->customerMock);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage')
            ->with('You have been automatically logged in.');

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('/')
            ->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }
}