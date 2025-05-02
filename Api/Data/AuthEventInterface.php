<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Api\Data;

/**
 * Interface for JWT authentication events
 * Defines the structure and methods for authentication events
 */
interface AuthEventInterface
{
    /**
     * @return string
     */
    public function getEventId(): string;

    /**
     * @param string $eventId
     * @return $this
     */
    public function setEventId(string $eventId): self;

    /**
     * @return string
     */
    public function getEventType(): string;

    /**
     * @param string $eventType
     * @return $this
     */
    public function setEventType(string $eventType): self;

    /**
     * @return string|int
     */
    public function getTimestamp();

    /**
     * @param string|int $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp): self;

    /**
     * @return string
     */
    public function getSourceDomain(): string;

    /**
     * @param string $sourceDomain
     * @return $this
     */
    public function setSourceDomain(string $sourceDomain): self;

    /**
     * @return string|null
     */
    public function getTargetDomain(): ?string;

    /**
     * @param string|null $targetDomain
     * @return $this
     */
    public function setTargetDomain(?string $targetDomain): self;

    /**
     * @return mixed[]
     */
    public function getUserInfo(): array;

    /**
     * @param mixed[] $userInfo
     * @return $this
     */
    public function setUserInfo(array $userInfo): self;

    /**
     * @return mixed[]
     */
    public function getRequestMetadata(): array;

    /**
     * @param mixed[] $requestMetadata
     * @return $this
     */
    public function setRequestMetadata(array $requestMetadata): self;

    /**
     * @return mixed[]
     */
    public function getSecurityMetadata(): array;

    /**
     * @param mixed[] $securityMetadata
     * @return $this
     */
    public function setSecurityMetadata(array $securityMetadata): self;

    /**
     * Convert event data to array
     *
     * @param array $keys
     * @return array
     */
    public function toArray(array $keys = []): array;
}