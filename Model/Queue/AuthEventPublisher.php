<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use TheDevKitchen\JwtCrossDomainAuth\Helper\LoggerHelper;
use TheDevKitchen\JwtCrossDomainAuth\Api\Data\AuthEventInterface;
use TheDevKitchen\JwtCrossDomainAuth\Api\Data\AuthEventInterfaceFactory;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

/**
 * Publisher for JWT authentication events
 * Handles publishing authentication events to the message queue
 */
class AuthEventPublisher
{
    /**
     * @var string Topic name for authentication events
     */
    private const TOPIC_NAME = 'jwt_auth.events';
    
    /**
     * @var PublisherInterface
     */
    private $publisher;
    
    /**
     * @var LoggerHelper
     */
    private $loggerHelper;

    /**
     * @var AuthEventInterfaceFactory
     */
    private $authEventFactory;
    
    /**
     * @var Config
     */
    private $config;

    /**
     * Constructor
     *
     * @param PublisherInterface $publisher Message queue publisher
     * @param LoggerHelper $loggerHelper Logger helper service
     * @param AuthEventInterfaceFactory $authEventFactory Factory for auth events
     * @param Config $config Module configuration
     */
    public function __construct(
        PublisherInterface $publisher,
        LoggerHelper $loggerHelper,
        AuthEventInterfaceFactory $authEventFactory,
        Config $config
    ) {
        $this->publisher = $publisher;
        $this->loggerHelper = $loggerHelper;
        $this->authEventFactory = $authEventFactory;
        $this->config = $config;
    }

    /**
     * Publish authentication event to the queue
     * Will log directly if queue is disabled via configuration
     *
     * @param array $eventData Authentication event data
     * @return void
     */
    public function publish(array $eventData): void
    {
        try {
            // Add timestamp if not already present
            if (!isset($eventData['timestamp'])) {
                $eventData['timestamp'] = time();
            }
            
            // Add unique event ID if not already present
            if (!isset($eventData['event_id'])) {
                $eventData['event_id'] = bin2hex(random_bytes(16));
            }

            // Check if queue is enabled in configuration
            if (!$this->config->isQueueEnabled()) {
                // If queue is disabled, just log the event and return
                $this->loggerHelper->log(
                    'Auth event processed (queue disabled): ' . ($eventData['event_type'] ?? 'auth.event'),
                    [
                        'event_id' => $eventData['event_id'],
                        'timestamp' => $eventData['timestamp'],
                        'source_domain' => $eventData['source_domain'] ?? 'unknown',
                        'target_domain' => $eventData['target_domain'] ?? null,
                        'customer_id' => $eventData['user_info']['customer_id'] ?? 'unknown'
                    ]
                );
                return;
            }
            
            // Create event object from data
            /** @var AuthEventInterface $authEvent */
            $authEvent = $this->authEventFactory->create();
            $authEvent->setEventId($eventData['event_id']);
            $authEvent->setEventType($eventData['event_type'] ?? 'auth.event');
            $authEvent->setTimestamp($eventData['timestamp']);
            $authEvent->setSourceDomain($eventData['source_domain'] ?? 'unknown');
            $authEvent->setTargetDomain($eventData['target_domain'] ?? null);
            $authEvent->setUserInfo($eventData['user_info'] ?? []);
            $authEvent->setRequestMetadata($eventData['request_metadata'] ?? []);
            $authEvent->setSecurityMetadata($eventData['security_metadata'] ?? []);
            
            // Publish the event to the queue
            $this->publisher->publish(self::TOPIC_NAME, $authEvent);
            
            // Log a simple message about the event being published
            $this->loggerHelper->log(
                'Auth event published to queue: ' . ($eventData['event_type'] ?? 'auth.event'),
                [
                    'event_id' => $eventData['event_id'],
                    'customer_id' => $eventData['user_info']['customer_id'] ?? 'unknown'
                ],
                'debug'
            );
        } catch (\Exception $e) {
            $this->loggerHelper->log(
                'Failed to process auth event: ' . $e->getMessage(),
                [
                    'exception' => $e->getMessage()
                ],
                'error'
            );
        }
    }
}