<?php

namespace Paymark\PaymarkClick\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment\Interceptor;
use Paymark\PaymarkClick\Helper\ApiHelper;
use Paymark\PaymarkClick\Helper\Helper;

/**
 * InitializeCommand
 */
class InitializeCommand implements CommandInterface
{

    /**
     * @var Helper
     */
    private $_helper;

    /**
     * @var ApiHelper
     */
    private $_apiHelper;

    /**
     * InitializeCommand constructor.
     *
     * @param Helper $helper
     * @param ApiHelper $apiHelper
     */
    public function __construct(
        Helper $helper,
        ApiHelper $apiHelper
    )
    {
        $this->_helper = $helper;
        $this->_apiHelper = $apiHelper;
    }

    /**
     * Basic initialize command to generate payment URL for
     * Paymark Click methods
     *
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null|void
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        $this->_helper->log(__METHOD__. ' execute');

        $orderState = $commandSubject['stateObject'];
        $paymentAction = $commandSubject['paymentAction'];

        $this->_helper->log(__METHOD__. ' action:' . $paymentAction);

        /** @var PaymentDataObject $paymentDO */
        $paymentDO = $commandSubject['payment'];

        /** @var OrderAdapterInterface $order */
        $order = $paymentDO->getOrder();

        /** @var Interceptor $payment */
        $payment = $paymentDO->getPayment();

        $this->_helper->log(__METHOD__. " redirect orderId: {$order->getOrderIncrementId()}");

        // generate redirect url
        try {
            $url = $this->_apiHelper->createPaymentUrl($payment, $orderState, $paymentAction);

            // save to additionalInformation for later
            $additionalInfo = $payment->getAdditionalInformation();
            $additionalInfo["PaymarkURL"] = $url;

            $payment->unsAdditionalInformation();
            $payment->setAdditionalInformation($additionalInfo);

            $this->_helper->log(__METHOD__. " set payment info with url");
        } catch(\Exception $e) {
            $this->_helper->log(__METHOD__. " initialize exception");
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
