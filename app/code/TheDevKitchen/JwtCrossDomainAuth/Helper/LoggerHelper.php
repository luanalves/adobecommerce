<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use TheDevKitchen\JwtCrossDomainAuth\Logger\Logger;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

class LoggerHelper extends AbstractHelper
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param Config $config
     */
    public function __construct(
        Context $context,
        Logger $logger,
        Config $config
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Log message if logging is enabled
     *
     * @param string $message
     * @param array $context
     * @param string $level
     * @return void
     */
    public function log(string $message, array $context = [], string $level = 'info'): void
    {
        if (!$this->config->isLoggingEnabled()) {
            return;
        }

        switch ($level) {
            case 'error':
                $this->logger->error($message, $context);
                break;
            case 'warning':
                $this->logger->warning($message, $context);
                break;
            case 'debug':
                $this->logger->debug($message, $context);
                break;
            case 'info':
            default:
                $this->logger->info($message, $context);
                break;
        }
    }
}