<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Queue\Consumer;

use Psr\Log\LoggerInterface;

/**
 * Consumer class for processing cross-domain authentication login events
 * Handles asynchronous logging of authentication events through RabbitMQ
 */
class LoginLogger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger PSR-3 compliant logger for recording authentication events
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Process a login event message from the queue
     * 
     * @param array $data The login event data containing customer and authentication details
     * @return void
     */
    public function process(array $data): void
    {
        if (isset($data['customer_id'])) {
            $this->logger->info('Cross-domain login successful for customer_id=' . $data['customer_id']);
        } else {
            $this->logger->warning('Incomplete cross-domain login data: ' . json_encode($data));
        }
    }
}