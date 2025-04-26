<?php
namespace TheDevKitchen\JwtCrossDomainAuth\Controller\Login;

use Magento\CardinalCommerce\Model\Response\JwtParserInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action; // Updated namespace for JwtParserInterface
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;

class Index extends Action
{
    protected $resultRedirectFactory;

    private $jwtParser;
    private $customerRepository;
    private $customerSession;
    private $publisher;

    public function __construct(
        Context $context,
        JwtParserInterface $jwtParser,
        CustomerRepositoryInterface $customerRepository,
        Session $customerSession,
        PublisherInterface $publisher,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->jwtParser = $jwtParser;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->publisher = $publisher;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function execute()
    {
        $jwt = $this->getRequest()->getParam('token');

        try {
            $parsedToken = $this->jwtParser->parse($jwt);
            $email = $parsedToken['email']; // Adjusted to match CardinalCommerce implementation
            $customer = $this->customerRepository->get($email);

            $this->customerSession->setCustomerDataAsLoggedIn($customer);

            $this->publisher->publish('crossdomain.auth.login', ['customer_id' => $customer->getId()]);

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl('/');
            return $resultRedirect;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(__('Authentication failed: %1', $e->getMessage()));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl('/customer/account/login');
            return $resultRedirect;
        }
    }
}
