<?php

namespace Paymark\PaymarkClick\Model\Api;

class ClickManagement
{

    /**
     *@var \Paymark\PaymarkClick\Helper\ApiHelper
     */
    private $_api;

    /**
     * @var \Paymark\PaymarkClick\Helper\Helper
     */
    private $_helper;

    /**
    *  @var \Magento\Checkout\Model\Session
    */
    private $_checkoutSession;

    /**
     * ClickManagement constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(\Magento\Checkout\Model\Session $checkoutSession)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->_helper = $objectManager->create("\Paymark\PaymarkClick\Helper\Helper");
        $this->_helper->log('Click management');

        $this->_api = $objectManager->create("\Paymark\PaymarkClick\Helper\ApiHelper");

        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Get Paymark redirect URL from saved payment information
     *
     * @return array|mixed|string
     */
    public function getRedirectLink()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $this->_helper->log(__METHOD__ . " redirect orderId: {$order->getEntityId()}");

        $payment = $order->getPayment();

        $additionalInfo = $payment->getAdditionalInformation();

        //@todo how to throw errors here?
        if (empty($additionalInfo["PaymarkURL"])) {
            $this->_helper->log(__METHOD__ . " no URL for : {$order->getEntityId()}");
            return "";
        }

        $paymentUrl = $additionalInfo["PaymarkURL"];
        if (empty($paymentUrl) || (is_array($paymentUrl) && count($paymentUrl) <= 0)) {
            $this->_helper->log(__METHOD__ . " no URL for : {$order->getEntityId()}");
            return "";
        }

        if (is_array($paymentUrl)) {
            $paymentUrl = reset($paymentUrl);
        }

        $this->_helper->log(__METHOD__ . " redirect url: {$paymentUrl}");

        return $paymentUrl;
    }
}