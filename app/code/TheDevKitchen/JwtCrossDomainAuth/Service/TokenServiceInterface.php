<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Service;

/**
 * Interface for JWT Token Service
 */
interface TokenServiceInterface
{
    /**
     * Generate a JWT token
     *
     * @param array $payload The data to include in the token
     * @param int|null $lifetime Token lifetime in seconds
     * @return string The generated token
     */
    public function generateToken(array $payload, ?int $lifetime = null): string;

    /**
     * Validate a JWT token
     *
     * @param string $token The token to validate
     * @return array Token data if valid
     * @throws \Magento\Framework\Exception\LocalizedException If token is invalid
     */
    public function validateToken(string $token): array;
}
