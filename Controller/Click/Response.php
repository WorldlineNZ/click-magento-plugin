<?php

namespace Onfire\Paymark\Controller\Click;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

abstract class Response extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    // disable CSRF protection on these inbound routes
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Handle response from Paymark
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $checkoutSession = $objectManager->get('\Magento\Checkout\Model\Session');
        $apiHelper = $objectManager->get("\Onfire\Paymark\Helper\ApiHelper");
        $helper = $objectManager->create("\Onfire\Paymark\Helper\Helper");

        $helper->log(__METHOD__. " execute response");

        $params = $this->getRequest()->getParams();

        //returned via "Display in Web Payments"
        if(empty($params) || (empty($params['Status']) && empty($params['status']))) {
            $helper->log(__METHOD__. " no response params, find order instead");

            $order = $checkoutSession->getLastRealOrder();

            // find transaction at Paymark
            $transaction = $apiHelper->findTransaction($order->getIncrementId());

            if(!$transaction) {
                // can't find transaction
                $helper->log(__METHOD__. " Unable to find transaction via search");
                $helper->addMessageError('Unable to find transaction');
                return $this->_redirect("checkout/cart");
            }

            // cast the transaction object to array
            if(!is_array($transaction)) {
                $transaction = (array)$transaction;
            }

            $params = $transaction;
        }

        if($result = $helper->processTransaction($params)) {
            $this->_redirect("checkout/onepage/success", [
                "_secure" => true
            ]);
        } else {
            $this->_redirect("checkout/cart");
        }
    }
}