<?php
namespace TranzzoPayment\Tranzzo\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

use TranzzoPayment\Tranzzo\Model\Tranzzo;

class OrderStatusAfterVoid implements ObserverInterface
{
	public function __construct(Tranzzo $modelTranzzo)
	{
		$this->modelTranzzo = $modelTranzzo;
	}

	public function execute(Observer $observer)
	{
		$payment = $observer->getPayment();
		if($payment instanceof Order\Payment && $payment->getMethod() == Tranzzo::CODE) {
			$order = $payment->getOrder();
			if($order->getState() != Order::STATE_CLOSED || $order->getState() != Order::STATE_CLOSED) {
				$order->setState(Order::STATE_CLOSED);
				$order->setStatus(Order::STATE_CLOSED);
				$order->save();
			}
		}
	}
}