<?php

namespace Paymark\PaymarkClick\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Paymark\PaymarkClick\Model\Adminhtml\Source\PaymentAction;

class ApiHelper extends AbstractHelper
{

    /**
     * @var \Paymark\PaymarkClick\Helper\Helper
     */
    private $_helper;

    /**
     * @var \Paymark\PaymarkClick\Model\PaymarkAPI
     */
    private $_paymarkApi;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    private $_objectManager;

    /**
     * ApiHelper constructor.
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);

        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->_helper = $this->_objectManager->create("\Paymark\PaymarkClick\Helper\Helper");

        $this->_paymarkApi = $this->_objectManager->create("\Paymark\PaymarkClick\Model\PaymarkAPI");
    }

    /**
     * Generate payment URL from Magento payment object and update order state
     *
     * @param \Magento\Sales\Model\Order\Payment\Interceptor $payment
     * @param \Magento\Framework\DataObject $orderState
     * @param $paymentAction
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentUrl(\Magento\Sales\Model\Order\Payment\Interceptor $payment, $orderState, $paymentAction)
    {
        $order = $payment->getOrder();

        $return = $this->_getUrl('paymark/click/response/', ['_secure' => true]);

        try {
            $authOnly = ($paymentAction == PaymentAction::ACTION_AUTHORIZE) ? true : false;

            $reference = $this->getStoreName() . ' Payment';

            $transaction = $this->_paymarkApi->createTransaction(
                $order->getBaseGrandTotal(),
                $return,
                $reference,
                $order->getIncrementId(),
                $authOnly
            );

            $this->_helper->log(__METHOD__. " Successfully generated redirect URL");

        } catch (\Exception $e) {
            $this->_helper->log(__METHOD__ . " Failed to generate redirect URL");
            $this->_helper->log($e->getMessage());

            throw new LocalizedException(__("Failed to generate redirect URL - please check your errors logs or contact support"));
        }

        $url = (string) $transaction; // convert SimpleXmlElement
        if (empty($url)){
            throw new LocalizedException(__("Failed to generate redirect URL - please check your errors logs or contact support"));
        }

        $orderState->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $orderState->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $orderState->setIsNotified(false);

        $this->_helper->log(__METHOD__. " Order set as pending");

        $order->setCanSendNewEmailFlag(false);
        $order->save();

        $this->_helper->log(__METHOD__. " Order saved");

        return $url;
    }

    /**
     * Attempt to search for transaction by increment id, for today.
     * If there is a successful transaction, return that one, otherwise
     * return the latest failed one
     *
     * @param $incrementId
     * @return bool
     */
    public function findTransaction($incrementId)
    {
        //@todo fix dates
        $transactions = $this->_paymarkApi->searchTransaction(
            date('Y-m-d 00:00:00', strtotime('-1 day')),
            date('Y-m-d 23:59:59', strtotime('+1 day')),
            $incrementId
        );

        if($transactions && count($transactions) > 0) {
            $returnTransaction = null;
            foreach($transactions as $transaction) {
                if(
                    $transaction->particular == $incrementId &&
                    $transaction->status == \Paymark\PaymarkClick\Helper\Helper::PAYMENT_SUCCESS
                ) {
                    //found successful transaction for this order
                    $returnTransaction = $transaction;
                    break;
                }
            }

            //no successfull transaction, so just return the latest one for this order (first in the list)
            if(!$returnTransaction) {
                $returnTransaction = reset($transactions);
            }

            return $returnTransaction;
        }

        return false;
    }

    /**
     * Get a transaction by id via the API
     *
     * @param $transactionId
     * @return mixed
     */
    public function getTransaction($transactionId)
    {
        return $this->_paymarkApi->getTransaction($transactionId);
    }

    /**
     * Return store name for payment reference
     *
     * @return mixed
     */
    public function getStoreName()
    {
        $storeManager = $this->_objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        return $storeManager->getStore()->getName();
    }

}
