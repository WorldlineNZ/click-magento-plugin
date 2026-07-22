<?php

namespace Paymark\PaymarkClick\Controller\Click;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Paymark\PaymarkClick\Helper\ApiHelper;
use Paymark\PaymarkClick\Helper\Helper;

class Response extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    /**
     * @var ApiHelper
     */
    private $_apiHelper;

    /**
     * @var Helper
     */
    private $_helper;

    /**
     * @var Helper
     */
    private $_checkoutSession;

    /**
     * Response constructor.
     *
     * @param Context $context
     * @param ApiHelper $apiHelper
     * @param Helper $helper
     */
    public function __construct(
        Context $context,
        ApiHelper $apiHelper,
        Helper $helper,
        Session $checkoutSession,
    )
    {
        parent::__construct($context);

        $this->_apiHelper = $apiHelper;
        $this->_helper = $helper;
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Handle response from Paymark
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $this->_helper->log(__METHOD__. " execute response");

        $params = $this->getRequest()->getParams();

        // note: requires "Return Option" set to "Post to Return URL" in the Click Merchant Console;
        // since v0.4.0 the "Display in Web Payments" return (no params, look up last real order) is
        // no longer supported due to an M2.4 problem retrieving the last real order on return

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $transaction = $this->_apiHelper->getTransaction($params['TransactionId']);

        // double check returned info contains valid transaction id
        if(!$transaction) {
            $this->_helper->log(__METHOD__. " Unable to find transaction");
            $this->_helper->addMessageError('Unable to find transaction');
            return $resultRedirect->setPath("checkout/cart");
        }

        $params = (array) $transaction;

        if($this->_helper->processTransaction($params)) {
            return $resultRedirect->setPath("checkout/onepage/success", [
                "_secure" => true
            ]);
        } else {
            return $resultRedirect->setPath("checkout/cart");
        }
    }

    // disable CSRF protection on these inbound routes
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
