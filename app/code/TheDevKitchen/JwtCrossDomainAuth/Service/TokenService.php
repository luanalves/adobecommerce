<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Service;

use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Generator;
use TheDevKitchen\JwtCrossDomainAuth\Model\Token\Validator;

/**
 * Implementation of JWT Token Service
 */
class TokenService implements TokenServiceInterface
{
    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @param Generator $generator
     * @param Validator $validator
     */
    public function __construct(
        Generator $generator,
        Validator $validator
    ) {
        $this->generator = $generator;
        $this->validator = $validator;
    }

    /**
     * @inheritDoc
     */
    public function generateToken(array $payload, ?int $lifetime = null): string
    {
        return $this->generator->generate($payload, $lifetime);
    }

    /**
     * @inheritDoc
     */
    public function validateToken(string $token): array
    {
        return $this->validator->validate($token);
    }
}
