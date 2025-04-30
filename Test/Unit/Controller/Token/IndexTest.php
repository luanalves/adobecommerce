<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Controller\Token;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TheDevKitchen\JwtCrossDomainAuth\Controller\Token\Index;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

class IndexTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var JsonFactory|MockObject
     */
    private $jsonFactoryMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    /**
     * @var TokenServiceInterface|MockObject
     */
    private $tokenServiceMock;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSessionMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var Json|MockObject
     */
    private $jsonResponseMock;

    /**
     * @var Customer|MockObject
     */
    private $customerMock;

    /**
     * @var Index
     */
    private $controller;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        
        $this->jsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->configMock = $this->createMock(Config::class);
        $this->tokenServiceMock = $this->createMock(TokenServiceInterface::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->jsonResponseMock = $this->createMock(Json::class);
        $this->customerMock = $this->createMock(Customer::class);
        
        $this->jsonFactoryMock->method('create')->willReturn($this->jsonResponseMock);
        
        $this->controller = $this->objectManager->getObject(Index::class, [
            'resultJsonFactory' => $this->jsonFactoryMock,
            'config' => $this->configMock,
            'tokenService' => $this->tokenServiceMock,
            'customerSession' => $this->customerSessionMock,
            'logger' => $this->loggerMock
        ]);
    }

    public function testExecuteWithModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);
            
        $this->jsonResponseMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'Cross-domain authentication is disabled.'
            ])
            ->willReturnSelf();
            
        $result = $this->controller->execute();
        
        $this->assertSame($this->jsonResponseMock, $result);
    }

    public function testExecuteWithCustomerNotLoggedIn()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
            
        $this->customerSessionMock->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(false);
            
        $this->jsonResponseMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'You must be logged in to switch stores.'
            ])
            ->willReturnSelf();
            
        $result = $this->controller->execute();
        
        $this->assertSame($this->jsonResponseMock, $result);
    }

    public function testExecuteSuccessful()
    {
        $customerId = 123;
        $customerEmail = 'customer@example.com';
        $customerName = 'John Doe';
        $expectedToken = 'generated.jwt.token';
        
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
            
        $this->customerSessionMock->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(true);
            
        $this->customerSessionMock->expects($this->once())
            ->method('getCustomer')
            ->willReturn($this->customerMock);
            
        $this->customerMock->expects($this->once())
            ->method('getId')
            ->willReturn($customerId);
            
        $this->customerMock->expects($this->once())
            ->method('getEmail')
            ->willReturn($customerEmail);
            
        $this->customerMock->expects($this->once())
            ->method('getName')
            ->willReturn($customerName);
            
        $this->tokenServiceMock->expects($this->once())
            ->method('generateToken')
            ->with($this->callback(function ($payload) use ($customerId, $customerEmail, $customerName) {
                return $payload['sub'] == $customerId && 
                       $payload['email'] == $customerEmail &&
                       $payload['name'] == $customerName &&
                       isset($payload['iss']) &&
                       isset($payload['iat']) &&
                       isset($payload['jti']);
            }))
            ->willReturn($expectedToken);
            
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'JWT token generated for cross-domain authentication',
                ['customer_id' => $customerId]
            );
            
        $this->jsonResponseMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'token' => $expectedToken
            ])
            ->willReturnSelf();
            
        $result = $this->controller->execute();
        
        $this->assertSame($this->jsonResponseMock, $result);
    }

    public function testExecuteWithTokenGenerationException()
    {
        $errorMessage = 'Error generating token';
        
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
            
        $this->customerSessionMock->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(true);
            
        $this->customerSessionMock->expects($this->once())
            ->method('getCustomer')
            ->willReturn($this->customerMock);
            
        $this->tokenServiceMock->expects($this->once())
            ->method('generateToken')
            ->willThrowException(new LocalizedException(__($errorMessage)));
            
        $this->loggerMock->expects($this->once())
            ->method('notice')
            ->with('JWT token generation failed: ' . $errorMessage);
            
        $this->jsonResponseMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => $errorMessage
            ])
            ->willReturnSelf();
            
        $result = $this->controller->execute();
        
        $this->assertSame($this->jsonResponseMock, $result);
    }
}