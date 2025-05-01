<?php
/**
 * @author      Luan Silva
 * @copyright   2025 The Dev Kitchen (https://www.thedevkitchen.com.br)
 * @license     https://www.thedevkitchen.com.br  Copyright
 */
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Plugin\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use TheDevKitchen\JwtCrossDomainAuth\Model\Config;

class BeforeDispatch
{
    /** @var Config */
    private $config;

    /** @var RedirectFactory */
    private $redirectFactory;

    public function __construct(Config $config, RedirectFactory $redirectFactory)
    {
        $this->config = $config;
        $this->redirectFactory = $redirectFactory;
    }

    public function beforeDispatch(Action $subject, RequestInterface $request)
    {
        if (!$this->config->isEnabled()) {
            $resultRedirect = $this->redirectFactory->create();
            $resultRedirect->setPath('no-route');
            return $resultRedirect;
        }
    }
}
