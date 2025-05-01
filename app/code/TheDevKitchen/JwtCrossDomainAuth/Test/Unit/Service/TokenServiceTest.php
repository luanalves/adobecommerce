<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Generator;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Validator;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenService;

class TokenServiceTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Generator|\PHPUnit\Framework\MockObject\MockObject
     */
    private $generatorMock;

    /**
     * @var Validator|\PHPUnit\Framework\MockObject\MockObject
     */
    private $validatorMock;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configMock;

    /**
     * @var TokenService
     */
    private $tokenService;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->generatorMock = $this->createMock(Generator::class);
        $this->validatorMock = $this->createMock(Validator::class);
        $this->configMock = $this->createMock(Config::class);
        
        // Default behavior - module enabled
        $this->configMock->method('isEnabled')
            ->willReturn(true);

        $this->tokenService = $this->objectManager->getObject(TokenService::class, [
            'generator' => $this->generatorMock,
            'validator' => $this->validatorMock,
            'config' => $this->configMock
        ]);
    }

    public function testGenerateToken()
    {
        $payload = [
            'sub' => '123',
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $lifetime = 3600;

        $expectedToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMiLCJlbWFpbCI6InRlc3RAZXhhbXBsZS5jb20iLCJuYW1lIjoiVGVzdCBVc2VyIn0.X';

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
        
        $this->generatorMock->expects($this->once())
            ->method('generate')
            ->with($payload, $lifetime)
            ->willReturn($expectedToken);

        $token = $this->tokenService->generateToken($payload, $lifetime);

        $this->assertEquals($expectedToken, $token);
    }

    public function testValidateToken()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMiLCJlbWFpbCI6InRlc3RAZXhhbXBsZS5jb20iLCJuYW1lIjoiVGVzdCBVc2VyIn0.X';

        $expectedClaims = [
            'sub' => '123',
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);
        
        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($token)
            ->willReturn($expectedClaims);

        $claims = $this->tokenService->validateToken($token);

        $this->assertEquals($expectedClaims, $claims);
    }

    public function testGenerateTokenWhenModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);
        
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cross-domain authentication is disabled.');
        
        $this->generatorMock->expects($this->never())
            ->method('generate');

        $this->tokenService->generateToken(['sub' => '123']);
    }

    public function testValidateTokenWhenModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);
        
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cross-domain authentication is disabled.');
        
        $this->validatorMock->expects($this->never())
            ->method('validate');

        $this->tokenService->validateToken('test.token.string');
    }
}
