<?php

namespace Onfire\PaymarkClick\Block;

use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{

    /**
     * Prepare payment information
     *
     * @param \Magento\Framework\DataObject|array|null $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);

        $fields = [
            'TransactionId' => 'Transaction ID',
            'Type' => 'Transaction Type',
            'Status' => 'Status',
            'CardType' => 'Card Type',
            'CardNumber' => 'Card Number',
            'CardExpiry' => 'Card Expiry'
        ];

        $payment = $this->getInfo();
        foreach ($fields as $fieldKey => $fieldName) {
            if ($payment->getAdditionalInformation($fieldKey) !== null) {
                $this->setDataToTransfer(
                    $transport,
                    $fieldName,
                    $payment->getAdditionalInformation($fieldKey)
                );
            }
        }

        return $transport;
    }
}
