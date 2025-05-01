<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * Test case for \TheDevKitchen\JwtCrossDomainAuth\Model\Config
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptorMock;

    /**
     * @var DeploymentConfig|MockObject
     */
    private $deploymentConfigMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->getMock();

        $this->encryptorMock = $this->getMockBuilder(EncryptorInterface::class)
            ->getMock();

        $this->deploymentConfigMock = $this->getMockBuilder(DeploymentConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->urlBuilderMock = $this->getMockBuilder(UrlInterface::class)
            ->getMock();

        $this->config = $objectManager->getObject(
            Config::class,
            [
                'scopeConfig' => $this->scopeConfigMock,
                'encryptor' => $this->encryptorMock,
                'deploymentConfig' => $this->deploymentConfigMock,
                'urlBuilder' => $this->urlBuilderMock
            ]
        );
    }

    /**
     * Test isEnabled method
     */
    public function testIsEnabled(): void
    {
        $storeId = 1;
        
        // Test enabled
        $this->scopeConfigMock->expects($this->at(0))
            ->method('isSetFlag')
            ->with(
                'jwt_crossdomain_auth/general/enabled',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled($storeId));
        
        // Test disabled
        $this->scopeConfigMock->expects($this->at(0))
            ->method('isSetFlag')
            ->with(
                'jwt_crossdomain_auth/general/enabled',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    /**
     * Test getTargetDomain method
     */
    public function testGetTargetDomain(): void
    {
        $baseUrl = 'https://example.com/magento/';
        $expectedDomain = 'example.com';

        $this->urlBuilderMock->expects($this->once())
            ->method('getBaseUrl')
            ->willReturn($baseUrl);

        $result = $this->config->getTargetDomain();
        
        $this->assertEquals($expectedDomain, $result);
    }

    /**
     * Test getJwtExpiration method
     */
    public function testGetJwtExpiration(): void
    {
        $expectedExpiration = 60; // 1 minute

        $result = $this->config->getJwtExpiration();
        
        $this->assertEquals($expectedExpiration, $result);
    }

    /**
     * Test getSecretKey method with key from deployment config
     */
    public function testGetSecretKeyFromDeploymentConfig(): void
    {
        $cryptKey = 'test_crypt_key_from_deployment_config';

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('crypt/key')
            ->willReturn($cryptKey);

        $this->encryptorMock->expects($this->never())
            ->method('getKey');

        $result = $this->config->getSecretKey();
        
        $this->assertEquals($cryptKey, $result);
    }

    /**
     * Test getSecretKey method with key from encryptor
     */
    public function testGetSecretKeyFromEncryptor(): void
    {
        $cryptKey = 'test_crypt_key_from_encryptor';

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('crypt/key')
            ->willReturn(null);

        $this->encryptorMock->expects($this->once())
            ->method('getKey')
            ->willReturn($cryptKey);

        $result = $this->config->getSecretKey();
        
        $this->assertEquals($cryptKey, $result);
    }

    /**
     * Test getSecretKey method with short key that needs hashing
     */
    public function testGetSecretKeyWithShortKey(): void
    {
        $shortKey = 'short_key'; // Less than 32 bytes
        $expectedHash = hash('sha256', $shortKey);

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('crypt/key')
            ->willReturn($shortKey);

        $result = $this->config->getSecretKey();
        
        $this->assertEquals($expectedHash, $result);
    }

    /**
     * Test getSecretKey method with null key
     */
    public function testGetSecretKeyWithNullKey(): void
    {
        $defaultHash = hash('sha256', 'magento-jwt-default');

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('crypt/key')
            ->willReturn(null);

        $this->encryptorMock->expects($this->once())
            ->method('getKey')
            ->willReturn(null);

        $result = $this->config->getSecretKey();
        
        $this->assertEquals($defaultHash, $result);
    }
}