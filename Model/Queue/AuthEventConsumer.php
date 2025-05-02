<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Queue;

use TheDevKitchen\JwtCrossDomainAuth\Helper\LoggerHelper;
use TheDevKitchen\JwtCrossDomainAuth\Api\Data\AuthEventInterface;

/**
 * Consumer for JWT authentication events
 * Processes authentication events from the message queue without database storage
 */
class AuthEventConsumer
{
    /**
     * @var LoggerHelper
     */
    private $loggerHelper;

    /**
     * Constructor
     *
     * @param LoggerHelper $loggerHelper Logger helper service
     */
    public function __construct(
        LoggerHelper $loggerHelper
    ) {
        $this->loggerHelper = $loggerHelper;
    }

    /**
     * Process authentication event from the queue
     * Simply logs the event details without storing to database
     *
     * @param AuthEventInterface $authEvent Authentication event
     * @return void
     */
    public function process(AuthEventInterface $authEvent): void
    {
        try {
            // Log the authentication event
            $this->loggerHelper->log(
                'Authentication event processed: ' . $authEvent->getEventType(),
                [
                    'event_id' => $authEvent->getEventId(),
                    'event_type' => $authEvent->getEventType(),
                    'timestamp' => $authEvent->getTimestamp(),
                    'source_domain' => $authEvent->getSourceDomain(),
                    'target_domain' => $authEvent->getTargetDomain(),
                    'customer_id' => $authEvent->getUserInfo()['customer_id'] ?? 'unknown'
                ]
            );
            
            // Additional processing can be added here if needed
            // For example, sending notifications, updating stats, etc.
            
        } catch (\Exception $e) {
            $this->loggerHelper->log(
                'Failed to process authentication event: ' . $e->getMessage(),
                [
                    'event_id' => $authEvent->getEventId() ?? 'unknown',
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                'error'
            );
        }
    }
}