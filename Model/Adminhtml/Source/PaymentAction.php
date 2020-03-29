<?php

namespace Onfire\PaymarkClick\Model\Adminhtml\Source;

/**
 * Class PaymentAction
 */
class PaymentAction implements \Magento\Framework\Data\OptionSourceInterface
{
    const ACTION_AUTHORIZE         = 'authorize';
    const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Authorise and Capture')
            ],
            [
                'value' => self::ACTION_AUTHORIZE,
                'label' => __('Authorise Only')
            ]
        ];
    }
}
