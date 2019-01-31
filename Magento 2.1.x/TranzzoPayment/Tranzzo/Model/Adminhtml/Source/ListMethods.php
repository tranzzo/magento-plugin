<?php
namespace TranzzoPayment\Tranzzo\Model\Adminhtml\Source;

use TranzzoPayment\Tranzzo\Model\Api\Payment as ApiTranzzo;
class ListMethods implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $list = [
            [
                'value' => ApiTranzzo::P_METHOD_PURCHASE,
                'label' => _('Purchase'),
            ],
            [
                'value' => ApiTranzzo::P_METHOD_AUTH,
                'label' => _('Authorize and Capture'),
            ],
        ];
        return $list;
    }
}
