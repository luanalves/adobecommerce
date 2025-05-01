<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Model\Token;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Jwt\JwtManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Validator;

class ValidatorTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var JwtManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $jwtManagerMock;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configMock;

    /**
     * @var Validator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->jwtManagerMock = $this->createMock(JwtManagerInterface::class);
        $this->configMock = $this->createMock(Config::class);

        $this->validator = $this->objectManager->getObject(Validator::class, [
            'jwtManager' => $this->jwtManagerMock,
            'config' => $this->configMock
        ]);
    }

    public function testValidateTokenWhenModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cross-domain authentication is disabled.');

        $this->validator->validate('test.token.string');
    }

    public function testValidateTokenWhenModuleEnabled()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' . 
                 base64_encode(json_encode(['sub' => '123', 'exp' => time() + 3600])) . 
                 '.signature';

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getSecretKey')
            ->willReturn('test_secret_key');

        $result = $this->validator->validate($token);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sub', $result);
        $this->assertEquals('123', $result['sub']);
    }
}