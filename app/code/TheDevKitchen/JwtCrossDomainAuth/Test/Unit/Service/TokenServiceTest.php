<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Service;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
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
     * @var TokenService
     */
    private $tokenService;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->generatorMock = $this->createMock(Generator::class);
        $this->validatorMock = $this->createMock(Validator::class);

        $this->tokenService = $this->objectManager->getObject(TokenService::class, [
            'generator' => $this->generatorMock,
            'validator' => $this->validatorMock
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

        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($token)
            ->willReturn($expectedClaims);

        $claims = $this->tokenService->validateToken($token);

        $this->assertEquals($expectedClaims, $claims);
    }
}
