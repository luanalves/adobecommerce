<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Logger;

use Magento\Framework\Filesystem\DriverInterface;
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
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     */
    public function __construct(
        Config $config,
        DriverInterface $filesystem,
        ?string $filePath = null
    ) {
        $this->config = $config;
        parent::__construct($filesystem, $filePath);
        $this->setFilePath($this->getFilePath());
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
