<?php

namespace Onfire\Paymark\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment\Interceptor;

/**
 * InitializeCommand
 */
class InitializeCommand implements CommandInterface
{

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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create("\Onfire\Paymark\Helper\Helper");
        $helper->log(__METHOD__. ' execute');

        /** @var \Onfire\Paymark\Helper\ApiHelper $apiHelper */
        $apiHelper = $objectManager->create("\Onfire\Paymark\Helper\ApiHelper");

        $orderState = $commandSubject['stateObject'];

        $paymentAction = $commandSubject['paymentAction'];
        $helper->log(__METHOD__. ' action:' . $paymentAction);

        /** @var PaymentDataObject $paymentDO */
        $paymentDO = $commandSubject['payment'];

        /** @var OrderAdapterInterface $order */
        $order = $paymentDO->getOrder();

        /** @var Interceptor $payment */
        $payment = $paymentDO->getPayment();

        $helper->log(__METHOD__. " redirect orderId: {$order->getOrderIncrementId()}");

        // generate redirect url
        try {
            $url = $apiHelper->createPaymentUrl($payment, $orderState, $paymentAction);

            // save to additionalInformation for later
            $additionalInfo = $payment->getAdditionalInformation();
            $additionalInfo["PaymarkURL"] = $url;

            $payment->unsAdditionalInformation();
            $payment->setAdditionalInformation($additionalInfo);

            $helper->log(__METHOD__. " set payment info with url");
        } catch(\Exception $e) {
            $helper->log(__METHOD__. " initialize exception");
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
