<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Interface definition for JWT token service
 * Defines contract for token generation and validation operations
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Service;

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface for JWT token operations
 * Provides contract for secure cross-domain authentication token handling
 */
interface TokenServiceInterface
{
    /**
     * Generate a new JWT token with specified payload
     * Creates a secure token for cross-domain authentication with customer data
     *
     * @param array $payload Data to include in the token (customer info, etc)
     * @param int|null $lifetime Optional token lifetime in seconds
     * @return string Generated JWT token
     * @throws LocalizedException If token generation fails
     */
    public function generateToken(array $payload, ?int $lifetime = null): string;

    /**
     * Validate a JWT token and extract its claims
     * Verifies token authenticity and expiration
     *
     * @param string $token JWT token to validate
     * @return array Validated claims from the token
     * @throws LocalizedException If token validation fails
     */
    public function validateToken(string $token): array;
}
