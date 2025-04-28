<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

/**
 * CLI Command to generate JWT tokens for testing
 */
class GenerateToken extends Command
{
    private const ARG_SUBJECT = 'subject';
    private const OPT_EXPIRATION = 'expiration';
    private const OPT_CLAIM = 'claim';

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
        $this->setName('thedevkitchen:jwt:generate')
            ->setDescription('Generate a JWT token for testing')
            ->addOption(
                self::ARG_SUBJECT,
                's',
                InputOption::VALUE_REQUIRED,
                'Subject identifier (e.g. customer ID)',
                'default_user'
            )
            ->addOption(
                self::OPT_EXPIRATION,
                'e',
                InputOption::VALUE_REQUIRED,
                'Token expiration time in seconds',
                3600
            )
            ->addOption(
                self::OPT_CLAIM,
                'c',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Custom claims in format name:value (can be used multiple times)'
            );

        $this->setHelp(<<<HELP
The <info>%command.name%</info> command generates a JWT token with custom claims for testing:

<info>Generate a token with default values:</info>
    %command.full_name%

<info>Generate a token with a custom subject:</info>
    %command.full_name% --subject=customer_123

<info>Generate a token that expires in 10 minutes:</info>
    %command.full_name% --expiration=600

<info>Generate a token with custom claims:</info>
    %command.full_name% --claim="email:test@example.com" --claim="role:admin"

<info>Combine all options:</info>
    %command.full_name% --subject=customer_123 --expiration=600 --claim="email:test@example.com"
HELP
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $subject = $input->getOption(self::ARG_SUBJECT);
            $expiration = (int) $input->getOption(self::OPT_EXPIRATION);
            $claimOptions = $input->getOption(self::OPT_CLAIM);
            
            $payload = [
                'sub' => $subject
            ];
            
            // Process custom claims
            if ($claimOptions) {
                foreach ($claimOptions as $claim) {
                    $parts = explode(':', $claim, 2);
                    if (count($parts) !== 2) {
                        $output->writeln(
                            "<error>Invalid claim format: {$claim}. Use name:value format.</error>"
                        );
                        continue;
                    }
                    
                    list($name, $value) = $parts;
                    // Try to auto-convert value types
                    if ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'false') {
                        $value = false;
                    } elseif (is_numeric($value)) {
                        $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
                    }
                    
                    $payload[$name] = $value;
                }
            }
            
            $output->writeln('<info>Generating JWT token with payload:</info>');
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT));
            $output->writeln('');
            
            // Generate token
            $token = $this->tokenService->generateToken($payload, $expiration);
            
            $output->writeln('<info>Token generated successfully!</info>');
            $output->writeln('');
            $output->writeln($token);
            $output->writeln('');
            
            // Show token details and expiration
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $payloadJson = base64_decode(strtr($tokenParts[1], '-_', '+/'));
                $decodedPayload = json_decode($payloadJson, true);
                
                if (isset($decodedPayload['exp'])) {
                    $expTime = new \DateTime();
                    $expTime->setTimestamp($decodedPayload['exp']);
                    $output->writeln('<info>Token expires at:</info> ' . $expTime->format('Y-m-d H:i:s'));
                    
                    $now = new \DateTime();
                    $diff = $expTime->getTimestamp() - $now->getTimestamp();
                    $output->writeln(sprintf('<info>Token valid for:</info> %d seconds (%d minutes)', $diff, floor($diff / 60)));
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error generating token: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}