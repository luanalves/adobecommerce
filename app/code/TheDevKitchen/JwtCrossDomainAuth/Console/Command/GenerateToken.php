<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheDevKitchen\JwtCrossDomainAuth\Service\TokenServiceInterface;

/**
 * CLI Command to generate and validate JWT tokens for testing
 */
class GenerateToken extends Command
{
    // Arguments and options
    private const ARG_ACTION = 'action';
    private const ARG_TOKEN = 'token';
    private const ARG_SUBJECT = 'subject';
    private const OPT_EXPIRATION = 'expiration';
    private const OPT_CLAIM = 'claim';
    private const OPT_VERBOSE = 'verbose';

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
        $this->setName('thedevkitchen:jwt:token')
            ->setDescription('Generate or validate JWT tokens for testing')
            ->addArgument(
                self::ARG_ACTION,
                InputArgument::REQUIRED,
                'Action to perform: "generate" or "validate"'
            )
            ->addArgument(
                self::ARG_TOKEN,
                InputArgument::OPTIONAL,
                'JWT token to validate (only used with validate action)'
            )
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

        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command manages JWT tokens for testing purposes.

<info>To generate a token:</info>
    %command.full_name% generate [options]

<info>Generate a token with default values:</info>
    %command.full_name% generate

<info>Generate a token with a custom subject:</info>
    %command.full_name% generate --subject=customer_123

<info>Generate a token that expires in 10 minutes:</info>
    %command.full_name% generate --expiration=600

<info>Generate a token with custom claims:</info>
    %command.full_name% generate --claim="email:test@example.com" --claim="role:admin"

<info>To validate a token:</info>
    %command.full_name% validate <token>

<info>Example of token validation:</info>
    %command.full_name% validate eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMifQ.xxx
HELP
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $action = $input->getArgument(self::ARG_ACTION);

            if ($action === 'generate') {
                return $this->generateToken($input, $output);
            } elseif ($action === 'validate') {
                return $this->validateToken($input, $output);
            } else {
                $output->writeln("<error>Invalid action. Use 'generate' or 'validate'.</error>");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    /**
     * Generate a JWT token
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function generateToken(InputInterface $input, OutputInterface $output): int
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

    /**
     * Validate a JWT token
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function validateToken(InputInterface $input, OutputInterface $output): int
    {
        $token = $input->getArgument(self::ARG_TOKEN);

        if (empty($token)) {
            $output->writeln('<error>Token is required for validation. Use: validate <token></error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Validating JWT token...</info>');
        $output->writeln('');

        try {
            // Validate token and get claims
            $claims = $this->tokenService->validateToken($token);

            $output->writeln('<info>✓ Token is valid!</info>');
            $output->writeln('');

            // Display token structure
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $output->writeln('<info>Token Structure:</info>');
                $output->writeln('- <comment>Header:</comment> ' . $tokenParts[0]);
                $output->writeln('- <comment>Payload:</comment> ' . $tokenParts[1]);
                $output->writeln('- <comment>Signature:</comment> ' . $tokenParts[2]);
                $output->writeln('');

                // Decode header
                $headerJson = base64_decode(strtr($tokenParts[0], '-_', '+/'));
                $header = json_decode($headerJson, true);

                if (is_array($header)) {
                    $output->writeln('<info>Header:</info>');
                    $output->writeln(json_encode($header, JSON_PRETTY_PRINT));
                    $output->writeln('');
                }
            }

            // Token claims/payload
            $output->writeln('<info>Token Claims:</info>');
            $output->writeln(json_encode($claims, JSON_PRETTY_PRINT));
            $output->writeln('');

            // Additional information
            if (isset($claims['iat'])) {
                $issuedAt = new \DateTime();
                $issuedAt->setTimestamp($claims['iat']);
                $output->writeln('<info>Issued at:</info> ' . $issuedAt->format('Y-m-d H:i:s'));
            }

            if (isset($claims['exp'])) {
                $expTime = new \DateTime();
                $expTime->setTimestamp($claims['exp']);
                $output->writeln('<info>Expires at:</info> ' . $expTime->format('Y-m-d H:i:s'));

                $now = new \DateTime();
                $diff = $expTime->getTimestamp() - $now->getTimestamp();

                if ($diff > 0) {
                    $output->writeln(sprintf(
                        '<info>Token valid for:</info> %d seconds (%d minutes, %d hours)',
                        $diff,
                        floor($diff / 60),
                        floor($diff / 3600)
                    ));
                } else {
                    $output->writeln('<comment>Note: Token has expired but signature is still valid</comment>');
                }
            }

            return Command::SUCCESS;
        } catch (LocalizedException $e) {
            $output->writeln('<error>✗ Token validation failed: ' . $e->getMessage() . '</error>');

            // Attempt to display partial info even for invalid tokens
            $this->displayInvalidTokenInfo($token, $output);

            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Error during validation: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Try to display some information about invalid tokens
     *
     * @param string $token
     * @param OutputInterface $output
     * @return void
     */
    private function displayInvalidTokenInfo(string $token, OutputInterface $output): void
    {
        try {
            $tokenParts = explode('.', $token);

            if (count($tokenParts) !== 3) {
                $output->writeln('<error>Token structure is invalid. Expected 3 parts (header.payload.signature)</error>');
                return;
            }

            $output->writeln('<comment>Token appears to be malformed, but attempting to decode parts:</comment>');
            $output->writeln('');

            // Try to decode header
            try {
                $headerJson = base64_decode(strtr($tokenParts[0], '-_', '+/'));
                $header = json_decode($headerJson, true);

                if (is_array($header)) {
                    $output->writeln('<info>Header:</info>');
                    $output->writeln(json_encode($header, JSON_PRETTY_PRINT));
                    $output->writeln('');
                } else {
                    $output->writeln('<comment>Could not decode header</comment>');
                }
            } catch (\Exception $e) {
                $output->writeln('<comment>Could not decode header: ' . $e->getMessage() . '</comment>');
            }

            // Try to decode payload
            try {
                $payloadJson = base64_decode(strtr($tokenParts[1], '-_', '+/'));
                $payload = json_decode($payloadJson, true);

                if (is_array($payload)) {
                    $output->writeln('<info>Payload:</info>');
                    $output->writeln(json_encode($payload, JSON_PRETTY_PRINT));
                    $output->writeln('');

                    // Check if token is expired
                    if (isset($payload['exp']) && $payload['exp'] < time()) {
                        $expTime = new \DateTime();
                        $expTime->setTimestamp($payload['exp']);
                        $output->writeln('<error>Token expired at: ' . $expTime->format('Y-m-d H:i:s') . '</error>');

                        $now = new \DateTime();
                        $diff = $now->getTimestamp() - $payload['exp'];
                        $output->writeln(sprintf(
                            '<error>Token expired %d seconds ago (%d minutes)</error>',
                            $diff,
                            floor($diff / 60)
                        ));
                    }
                } else {
                    $output->writeln('<comment>Could not decode payload</comment>');
                }
            } catch (\Exception $e) {
                $output->writeln('<comment>Could not decode payload: ' . $e->getMessage() . '</comment>');
            }

        } catch (\Exception $e) {
            // Silent catch - this is just a best effort to show information
        }
    }
}
