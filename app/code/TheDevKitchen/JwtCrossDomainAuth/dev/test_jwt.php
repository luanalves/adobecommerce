<?php
/**
 * Test script for JWT Token Service
 * 
 * Usage: php app/code/TheDevKitchen/JwtCrossDomainAuth/dev/test_jwt.php
 */

use Magento\Framework\App\Bootstrap;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

// Initialize Magento application
require __DIR__ . '/../../../../app/bootstrap.php';

// Use the correct path to bootstrap
$bootstrapPath = BP;
if (!defined('BP')) {
    // Define BP if not already defined
    define('BP', realpath(__DIR__ . '/../../../../'));
}

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();

// Get the token service
try {
    /** @var TokenServiceInterface $tokenService */
    $tokenService = $objectManager->get(TokenServiceInterface::class);
    
    echo "=== JWT Token Service Test ===\n\n";
    
    // Test payload
    $payload = [
        'sub' => '123',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'roles' => ['customer'],
        'custom_data' => [
            'customer_id' => 12345,
            'store_id' => 1
        ]
    ];
    
    echo "Generating token with payload:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";
    
    // Generate token
    $token = $tokenService->generateToken($payload);
    echo "Generated token:\n{$token}\n\n";
    
    // Validate token
    echo "Validating token...\n";
    $claims = $tokenService->validateToken($token);
    
    echo "Token validated successfully!\n\n";
    echo "Token claims:\n";
    echo json_encode($claims, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test with custom expiration
    echo "Testing token with short expiration (10 seconds)...\n";
    $shortLifetimeToken = $tokenService->generateToken($payload, 10);
    $shortTokenClaims = $tokenService->validateToken($shortLifetimeToken);
    
    echo "Short-lived token validated successfully!\n";
    echo "Expiration timestamp: " . $shortTokenClaims['exp'] . "\n";
    echo "Current timestamp: " . time() . "\n";
    echo "Token will expire in " . ($shortTokenClaims['exp'] - time()) . " seconds\n\n";
    
    // Test invalid token
    echo "Testing invalid token validation...\n";
    try {
        $invalidToken = $token . 'invalid';
        $tokenService->validateToken($invalidToken);
        echo "Error: Invalid token was incorrectly validated!\n";
    } catch (\Exception $e) {
        echo "Expected error caught: " . $e->getMessage() . "\n\n";
    }
    
    echo "All tests completed successfully!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}