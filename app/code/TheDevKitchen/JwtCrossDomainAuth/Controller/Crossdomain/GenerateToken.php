<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 *
 * Controller for token generation in cross-domain authentication
 * Handles requests to generate JWT tokens for authenticated users
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Crossdomain;

use TheDevKitchen\JwtCrossDomainAuth\Service\TokenService;

/**
 * Token generation controller class
 * Provides endpoint for generating JWT tokens for cross-domain authentication
 */
class GenerateToken
{
    /**
     * Token service for JWT operations
     * @var TokenService
     */
    private TokenService $tokenService;

    /**
     * Constructor
     * 
     * @param TokenService $tokenService Service for JWT token operations
     */
    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Generate a JWT token for a specific user
     * Creates a secure token for cross-domain authentication
     *
     * @param string $userId The ID of the user to generate token for
     * @return string Generated JWT token
     */
    public function generate(string $userId): string
    {
        return $this->tokenService->createToken($userId);
    }
}