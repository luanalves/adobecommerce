<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

/**
 * CLI Command to test JWT Token Service
 */
class TestJwtService extends Command
{
    /**
     * @var TokenServiceInterface
     */
    private $tokenService;

    /**
     * @param TokenServiceInterface $tokenService
     * @param string|null $name
     */
    public function __construct(
        TokenServiceInterface $tokenService,
        string $name = null
    ) {
        parent::__construct($name);
        $this->tokenService = $tokenService;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('thedevkitchen:jwt:test')
            ->setDescription('Test the JWT Token Service implementation');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>=== JWT Token Service Test ===</info>');
            $output->writeln('');
            
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
            
            $output->writeln('<info>Generating token with payload:</info>');
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT));
            $output->writeln('');
            
            // Generate token
            $token = $this->tokenService->generateToken($payload);
            $output->writeln('<info>Generated token:</info>');
            $output->writeln($token);
            $output->writeln('');
            
            // Validate token
            $output->writeln('<info>Validating token...</info>');
            $claims = $this->tokenService->validateToken($token);
            
            $output->writeln('<info>Token validated successfully!</info>');
            $output->writeln('');
            $output->writeln('<info>Token claims:</info>');
            $output->writeln(json_encode($claims, JSON_PRETTY_PRINT));
            $output->writeln('');
            
            // Test with custom expiration
            $output->writeln('<info>Testing token with short expiration (10 seconds)...</info>');
            $shortLifetimeToken = $this->tokenService->generateToken($payload, 10);
            $shortTokenClaims = $this->tokenService->validateToken($shortLifetimeToken);
            
            $output->writeln('<info>Short-lived token validated successfully!</info>');
            $output->writeln('Expiration timestamp: ' . $shortTokenClaims['exp']);
            $output->writeln('Current timestamp: ' . time());
            $output->writeln('Token will expire in ' . ($shortTokenClaims['exp'] - time()) . ' seconds');
            $output->writeln('');
            
            // Test invalid token
            $output->writeln('<info>Testing invalid token validation...</info>');
            try {
                $invalidToken = $token . 'invalid';
                $this->tokenService->validateToken($invalidToken);
                $output->writeln('<error>Error: Invalid token was incorrectly validated!</error>');
            } catch (\Exception $e) {
                $output->writeln('<comment>Expected error caught: ' . $e->getMessage() . '</comment>');
                $output->writeln('');
            }
            
            $output->writeln('<info>All tests completed successfully!</info>');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}