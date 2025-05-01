<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = MonologLogger::INFO;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     * @param string $filePath
     */
    public function __construct(
        Config $config,
        $filePath = null
    ) {
        $this->config = $config;
        parent::__construct($filePath);
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record): bool
    {
        return $this->config->isLoggingEnabled() && parent::isHandling($record);
    }

    /**
     * Get the file path for logging
     *
     * @return string
     */
    protected function getFilePath(): string
    {
        $fileName = $this->config->getLogFileName() ?: 'jwt_auth.log';
        return BP . '/var/log/' . $fileName;
    }
}