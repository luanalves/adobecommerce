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
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Generator;

class GeneratorTest extends TestCase
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
     * @var Generator
     */
    private $generator;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->jwtManagerMock = $this->createMock(JwtManagerInterface::class);
        $this->configMock = $this->createMock(Config::class);

        $this->generator = $this->objectManager->getObject(Generator::class, [
            'jwtManager' => $this->jwtManagerMock,
            'config' => $this->configMock
        ]);
    }

    public function testGenerateTokenWhenModuleDisabled()
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cross-domain authentication is disabled.');

        $this->generator->generate(['sub' => '123']);
    }

    public function testGenerateTokenWhenModuleEnabled()
    {
        $payload = [
            'sub' => '123',
            'email' => 'test@example.com'
        ];

        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getJwtExpiration')
            ->willReturn(300);

        $this->configMock->expects($this->once())
            ->method('getSecretKey')
            ->willReturn('test_secret_key');

        $result = $this->generator->generate($payload);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(2, substr_count($result, '.'), 'JWT token should contain exactly two dots');
    }
}