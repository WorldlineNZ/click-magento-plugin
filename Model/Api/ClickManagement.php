<?php

namespace Paymark\PaymarkClick\Model\Api;

use Magento\Checkout\Model\Session;
use Paymark\PaymarkClick\Helper\ApiHelper;
use Paymark\PaymarkClick\Helper\Helper;

class ClickManagement
{

    /**
     *@var ApiHelper
     */
    private $_api;

    /**
     * @var Helper
     */
    private $_helper;

    /**
    *  @var Session
    */
    private $_checkoutSession;

    /**
     * @param Session $checkoutSession
     * @param Helper $paymarkHelper
     * @param ApiHelper $apiHelper
     */
    public function __construct(
        Session $checkoutSession,
        Helper $paymarkHelper,
        ApiHelper $apiHelper,
    )
    {
        $this->_helper = $paymarkHelper;
        $this->_api = $apiHelper;
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
