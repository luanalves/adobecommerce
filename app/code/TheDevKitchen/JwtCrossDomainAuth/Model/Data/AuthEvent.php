<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Model\Data;

use Magento\Framework\DataObject;
use TheDevKitchen\JwtCrossDomainAuth\Api\Data\AuthEventInterface;

/**
 * Authentication event data model
 * Implementation of the AuthEventInterface
 */
class AuthEvent extends DataObject implements AuthEventInterface
{
    /**
     * @inheritDoc
     */
    public function getEventId(): string
    {
        return $this->getData('event_id');
    }

    /**
     * @inheritDoc
     */
    public function setEventId(string $eventId): AuthEventInterface
    {
        return $this->setData('event_id', $eventId);
    }

    /**
     * @inheritDoc
     */
    public function getEventType(): string
    {
        return $this->getData('event_type');
    }

    /**
     * @inheritDoc
     */
    public function setEventType(string $eventType): AuthEventInterface
    {
        return $this->setData('event_type', $eventType);
    }

    /**
     * @inheritDoc
     */
    public function getTimestamp()
    {
        return $this->getData('timestamp');
    }

    /**
     * @inheritDoc
     */
    public function setTimestamp($timestamp): AuthEventInterface
    {
        return $this->setData('timestamp', $timestamp);
    }

    /**
     * @inheritDoc
     */
    public function getSourceDomain(): string
    {
        return $this->getData('source_domain');
    }

    /**
     * @inheritDoc
     */
    public function setSourceDomain(string $sourceDomain): AuthEventInterface
    {
        return $this->setData('source_domain', $sourceDomain);
    }

    /**
     * @inheritDoc
     */
    public function getTargetDomain(): ?string
    {
        return $this->getData('target_domain');
    }

    /**
     * @inheritDoc
     */
    public function setTargetDomain(?string $targetDomain): AuthEventInterface
    {
        return $this->setData('target_domain', $targetDomain);
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): array
    {
        return $this->getData('user_info') ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setUserInfo(array $userInfo): AuthEventInterface
    {
        return $this->setData('user_info', $userInfo);
    }

    /**
     * @inheritDoc
     */
    public function getRequestMetadata(): array
    {
        return $this->getData('request_metadata') ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setRequestMetadata(array $requestMetadata): AuthEventInterface
    {
        return $this->setData('request_metadata', $requestMetadata);
    }

    /**
     * @inheritDoc
     */
    public function getSecurityMetadata(): array
    {
        return $this->getData('security_metadata') ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setSecurityMetadata(array $securityMetadata): AuthEventInterface
    {
        return $this->setData('security_metadata', $securityMetadata);
    }

    /**
     * @inheritDoc
     */
    public function toArray(array $keys = []): array
    {
        if (empty($keys)) {
            return [
                'event_id' => $this->getEventId(),
                'event_type' => $this->getEventType(),
                'timestamp' => $this->getTimestamp(),
                'source_domain' => $this->getSourceDomain(),
                'target_domain' => $this->getTargetDomain(),
                'user_info' => $this->getUserInfo(),
                'request_metadata' => $this->getRequestMetadata(),
                'security_metadata' => $this->getSecurityMetadata()
            ];
        }

        return parent::toArray($keys);
    }
}
