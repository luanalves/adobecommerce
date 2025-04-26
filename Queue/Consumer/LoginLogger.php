<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Queue\Consumer;

use Psr\Log\LoggerInterface;

class LoginLogger
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): void
    {
        if (isset($data['customer_id'])) {
            $this->logger->info('Login crossdomain para customer_id=' . $data['customer_id']);
        } else {
            $this->logger->warning('Dados de login crossdomain incompletos: ' . json_encode($data));
        }
    }
}