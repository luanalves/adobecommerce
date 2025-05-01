<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration model for JWT Cross-Domain Authentication
 */
class Config
{
    private const XML_PATH_ENABLED = 'jwt_crossdomain_auth/general/enabled';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param DeploymentConfig $deploymentConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        DeploymentConfig $deploymentConfig,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->deploymentConfig = $deploymentConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Check if the module is enabled
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return (bool)$this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get target domain for JWT token audience
     * Para uma autenticação bidirecional, utilizamos o domínio base da loja atual
     * como parte da audiência, permitindo verificação mais segura que usando '*'
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getTargetDomain($storeId = null): string
    {
        // Usa o domínio base atual como valor de audiência
        // Em uma autenticação bidirecional, ambos domínios devem reconhecer um ao outro
        return parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * Get JWT expiration time in seconds
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getJwtExpiration($storeId = null): int
    {
        // Valor padrão de 1 minuto (60 segundos)
        return 60;
    }

    /**
     * Get JWT secret key usando a chave de criptografia nativa do Magento
     * Este método centraliza a lógica de segurança para geração e validação de tokens
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getSecretKey($storeId = null): string
    {
        // Utiliza a chave de criptografia nativa do Magento
        $cryptKey = null;

        // Tenta obter a chave do deployment config primeiro (mais eficiente)
        $cryptKey = $this->deploymentConfig->get('crypt/key');

        // Se não conseguir, usa o encryptor como fallback
        if (!$cryptKey) {
            $cryptKey = $this->encryptor->getKey();
        }

        // Garante que temos uma chave segura de pelo menos 32 bytes
        if (!$cryptKey || strlen($cryptKey) < 32) {
            // Usa SHA-256 para garantir o tamanho adequado
            $cryptKey = hash('sha256', $cryptKey ?: 'magento-jwt-default');
        }

        return $cryptKey;
    }
}
