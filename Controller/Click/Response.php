<?php

namespace Paymark\PaymarkClick\Controller\Click;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Response extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
        $apiHelper = $objectManager->get("\Paymark\PaymarkClick\Helper\ApiHelper");
        $helper = $objectManager->create("\Paymark\PaymarkClick\Helper\Helper");

        $helper->log(__METHOD__. " execute response");

        $params = $this->getRequest()->getParams();

        // returned via "Display in Web Payments"
        // since v0.4.0: M2.4 has a problem with retrieving last real order on return, so this is no longer working
        /*if (empty($params) || (empty($params['Status']) && empty($params['status']))) {
            $helper->log(__METHOD__ . " no response params, find order instead");

            $order = $checkoutSession->getLastRealOrder();

            // find transaction at Paymark
            $transaction = $apiHelper->findTransaction($order->getIncrementId());

            if (!$transaction) {
                // can't find transaction
                $helper->log(__METHOD__ . " Unable to find transaction via search");
                $helper->addMessageError('Unable to find transaction');
                return $this->_redirect("checkout/cart");
            }

            // cast the transaction object to array
            if (!is_array($transaction)) {
                $transaction = (array)$transaction;
            }

            $params = $transaction;
        } else {
            $transaction = $apiHelper->getTransaction($params['TransactionId']);
            if (!$transaction) {
                $helper->log(__METHOD__ . " Unable to find transaction");
                $helper->addMessageError('Unable to find transaction');
                return $this->_redirect("checkout/cart");
            }

            $params = (array)$transaction;
        }*/

        $transaction = $apiHelper->getTransaction($params['TransactionId']);

        // double check returned info contains valid transaction id
        if(!$transaction) {
            $helper->log(__METHOD__. " Unable to find transaction");
            $helper->addMessageError('Unable to find transaction');
            return $this->_redirect("checkout/cart");
        }

        $params = (array) $transaction;

        if($result = $helper->processTransaction($params)) {
            $this->_redirect("checkout/onepage/success", [
                "_secure" => true
            ]);
        } else {
            $this->_redirect("checkout/cart");
        }
    }
}
