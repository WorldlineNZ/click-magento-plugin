<?php

namespace Paymark\PaymarkClick\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Store\Model\ScopeInterface;

class Helper
{

    /**
     * @var ScopeConfigInterface
     */
    private $_config;

    /**
     * @var ObjectManager
     */
    private $_objectManager;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    private $_transactionBuilder;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $_quoteRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;

    /**
     * @var HistoryFactory
     */
    private $_orderHistoryFactory;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $_messageManager;

    /**
     * @var  \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $_orderSender;

    /**
     * @var \Paymark\PaymarkClick\Logger\PaymentLogger
     */
    private $_logger;

    const CONFIG_PREFIX = 'payment/paymark/';

    const PAYMENT_SUCCESS = 'SUCCESSFUL';

    const PAYMENT_UNKNOWN = 'UNKNOWN';

    const TYPE_PURCHASE = 'PURCHASE';

    const TYPE_AUTH = 'AUTHORISATION';

    const TYPE_OE_PAYMENT = 'OE_PAYMENT';

    /**
     * Helper constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param HistoryFactory $orderHistoryFactory
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        HistoryFactory $orderHistoryFactory,
        CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        $this->_config = $scopeConfig;

        $this->_orderHistoryFactory = $orderHistoryFactory;

        $this->_orderSender = $orderSender;

        $this->_objectManager = ObjectManager::getInstance();

        $this->_quoteRepository = $quoteRepository;

        $this->_messageManager = $messageManager;

        $this->_transactionBuilder = $this->_objectManager->get('\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface');

        $this->_checkoutSession = $this->_objectManager->get('\Magento\Checkout\Model\Session');

        $this->_logger = $this->_objectManager->get("\Paymark\PaymarkClick\Logger\PaymentLogger");
    }

    /**
     * Log to Paymark file
     *
     * @param $message
     */
    public function log($message)
    {
        if (!$this->_logger) {
            return;
        }

        // only log info if debug_log is true
        if($this->getConfig('debug_log')) {
            $this->_logger->info($message);
        }
    }

    /**
     * Get Paymark system config
     *
     * @param $path
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->_config->getValue(
            self::CONFIG_PREFIX . $path,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Process response from Paymark, updating order as required
     *
     * @param $params
     * @return bool
     */
    public function processTransaction($params) {
        $this->log(__METHOD__. " handle transaction");

        if(empty($params) || (empty($params['Status']) && empty($params['status']))) {
            $this->log(__METHOD__. " no response params, something has gone quite wrong.");
            $this->addMessageError('Unable to load order.');
            return false;
        }

        $status = $this->getParamInsensitive('Status', $params);
        $incrementId = $this->getParamInsensitive('Reference', $params);//increment id for transaction

        if(!$incrementId) {
            //no id, what happened?
            $this->log(__METHOD__. " increment ID missing for payment.");
            $this->addMessageError('Unable to load order.');
            return false;
        }

        $order = $this->_getOrderByIncrementId($incrementId);
        if(!$order) {
            //no order, what happened?
            $this->log(__METHOD__. " cant find order for increment id: " . $incrementId);
            $this->addMessageError('Unable to load order.');
            return false;
        }

        $payment = $order->getPayment();

        try {
            if($status == self::PAYMENT_UNKNOWN) {
                // unknown status, what do we do here?
                //@todo handle unknown response - do we wait and retry?
                $this->addMessageError('Payment failed with unknown error');

                return false;

            } else if($status == self::PAYMENT_SUCCESS) {
                // payment completed
                $this->log(__METHOD__. " " . $incrementId . " order status complete");

                $this->_orderSuccess($order, $payment, $params);

                $this->log(__METHOD__. " " . $incrementId . " payment complete");

                $this->_setPaymentInformation($payment, $params);

                $this->log(__METHOD__. " " . $incrementId . " set payment info back on order");

                $this->sendOrderEmail($order);

                return true;

            } else {
                // payment failed
                $this->log(__METHOD__. " " . $incrementId . " order status failed");

                $errorCode = $this->getParamInsensitive('ErrorCode', $params);
                $errorMessage = $this->getParamInsensitive('ErrorMessage', $params);

                $this->log(__METHOD__. " " . $incrementId . " error: (" . $errorCode . ") " . $errorMessage);

                $this->_orderFailed($order);

                $this->log(__METHOD__. " " . $incrementId . " quote rolled back, ready to redirect to cart");

                $this->addMessageError('Payment failed with error: ' . $errorMessage);

                return false;
            }
        } catch (\Exception $e) {
            $this->addMessageError('Payment failed with error: ' . $e->getMessage());
            $this->log(__METHOD__. " " . $incrementId . " " . $e->getMessage());
            return false;
        }

    }

    /**
     * Send order completed / paid email to customer
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function sendOrderEmail(\Magento\Sales\Model\Order $order)
    {
        if ($order->getCanSendNewEmailFlag()) {
            try {
                $this->_orderSender->send($order);
            } catch (\Exception $e) {
                $this->log(__METHOD__. " " . $order->getIncrementId() . " failed to send order email");
                $this->log($e->getMessage());
            }
        }
    }

    /**
     * Handle successful order for both AUTH and CAPTURE
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param $params
     * @return \Magento\Sales\Model\Order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function _orderSuccess(\Magento\Sales\Model\Order $order, \Magento\Sales\Model\Order\Payment $payment, $params)
    {
        $type = $this->getParamInsensitive('Type', $params);
        $transID =  $this->getParamInsensitive('TransactionId', $params);
        $amount = $this->getParamInsensitive('Amount', $params);
        $surcharge = $this->getParamInsensitive('surcharge', $params);

        // if there has been a surcharge, remove it from the total amount
        $amountFinal = (!empty($surcharge) && $surcharge > 0) ? ($amount - $surcharge) : $amount;

        // multiply totals by 100 to get integers for comparison
        $amountCheck = bcmul($amountFinal, 100);
        $orderTotalCheck = bcmul($order->getGrandTotal(), 100);

        // check if the order amount and the total charge amount match
        if($amountCheck != $orderTotalCheck) {
            throw new \Exception('Payment and order totals do not match');
        }

        $order->setCanSendNewEmailFlag(true);

        if ($type == self::TYPE_PURCHASE || $type == self::TYPE_OE_PAYMENT) {

            // prepare invoice and update order status
            $invoice = $order->prepareInvoice();
            $invoice->getOrder()->setIsInProcess(true);

            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

            $invoice->setTransactionId($transID);
            $invoice->register()->pay()->save();

            // create a capture transaction
            $this->_createTransaction($order, $payment, $transID, $type, true);

            $order->save();

            // add comment to order history
            $message = __(
                'Captured and invoiced amount of %1 for transaction %2',
                $amountFinal,
                $transID
            );

            $history = $this->_orderHistoryFactory->create()
                ->setComment($message)
                ->setEntityName('order')
                ->setOrder($order);

            $history->save();

            return $order;

        } else if ($type == self::TYPE_AUTH) {

            // just create a auth transaction to show it's been captured
            $this->_createTransaction($order, $payment, $transID, $type, false);

            return $order;
        }
    }

    /**
     * Order failed, cancel order and reinstate quote
     *
     * @todo this should be merged into a helper module along with Paymark OE
     *
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Sales\Model\Order
     * @throws \Exception
     */
    private function _orderFailed(\Magento\Sales\Model\Order $order)
    {
        // reset cart back into current session to retry
        if ($this->_restoreQuoteFromOrder($order)) {
            $this->log(__METHOD__ . " Quote has been rolled back.");
        } else {
            $this->log(__METHOD__ . " Unable to rollback quote.");
        }

        $order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_CANCEL, true);
        $order->cancel()->save();

        return $order;
    }

    /**
     * Restore quote from order when the payment failed
     *
     * @todo this should be merged into a helper module along with Paymark OE
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function _restoreQuoteFromOrder(\Magento\Sales\Model\Order $order)
    {
        try {
            $quote = $this->_quoteRepository->get($order->getQuoteId());
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->_quoteRepository->save($quote);
            $this->_checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->log($e->getMessage());
        }

        return false;
    }

    /**
     * Update payment info with additional response data
     *
     * @param $payment
     * @param $params
     */
    private function _setPaymentInformation($payment, $params)
    {
        $info = $payment->getAdditionalInformation();

        unset($info["PaymarkURL"]);

        $info['TransactionId'] = $this->getParamInsensitive('TransactionId', $params);
        $info['Type'] = $this->getParamInsensitive('Type', $params);
        $info['AccountId'] = $this->getParamInsensitive('AccountId', $params);
        $info['Status'] = $this->getParamInsensitive('Status', $params);
        $info['Reference'] = $this->getParamInsensitive('Reference', $params);
        $info['CardType'] = $this->getParamInsensitive('CardType', $params);
        $info['CardNumber'] = $this->getParamInsensitive('CardNumber', $params);
        $info['CardExpiry'] = $this->getParamInsensitive('CardExpiry', $params);
        //$info['CardHolder'] = $this->getParamInsensitive('CardHolder', $params);

        $payment->unsAdditionalInformation();
        $payment->setAdditionalInformation($info);

        $payment->save();
    }

    /**
     * Create order transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param $transId
     * @param $transType
     * @param $completed
     * @return \Magento\Sales\Api\Data\TransactionInterface
     */
    private function _createTransaction(
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Payment $payment,
        $transId,
        $transType,
        $completed)
    {
        if($transType == self::TYPE_AUTH) {
            $type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
        } else {
            $type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
        }

        $transaction = $this->_transactionBuilder
            ->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($transId)
            ->setFailSafe(true)
            ->build($type);

        $transaction->setIsClosed($completed);

        $transaction->save();

        return $transaction;
    }


    /**
     * Get order by increment id
     *
     * @param $incrementId
     * @return mixed
     */
    private function _getOrderByIncrementId($incrementId)
    {
        $collection = $this->_objectManager->create('Magento\Sales\Model\Order');
        $orderInfo = $collection->loadByIncrementId($incrementId);

        return $orderInfo->getId() ? $orderInfo : null;
    }

    /**
     * Add error message to session to display back to user
     *
     * @param $errorMessage
     */
    public function addMessageError($errorMessage) {
        $this->_messageManager->addErrorMessage($errorMessage);
    }

    /**
     * Find assoc array param case insensitively (paymark responses are randomly upper/lower)
     *
     * @param $needle
     * @param $haystack
     * @return bool
     */
    public function getParamInsensitive($needle, $haystack) {
        foreach ($haystack as $key => $value) {
            if (strtolower($needle) == strtolower($key)) {
                return $value;
            }
        }
        return null;
    }

}
