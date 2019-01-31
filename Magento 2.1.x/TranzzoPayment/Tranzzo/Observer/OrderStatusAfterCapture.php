<?php
namespace TranzzoPayment\Tranzzo\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

use TranzzoPayment\Tranzzo\Model\Tranzzo;

class OrderStatusAfterCapture implements ObserverInterface
{
	public function __construct(Tranzzo $modelTranzzo)
	{
		$this->modelTranzzo = $modelTranzzo;
	}

	public function execute(Observer $observer)
	{
		$invoice = $observer->getInvoice();
		if($invoice instanceof Order\Invoice) {
			$order = $invoice->getOrder();
			if($order->getPayment()->getMethod() == Tranzzo::CODE) {
				if ($order->getState() != Order::STATE_COMPLETE || $order->getState() != Order::STATE_COMPLETE) {
					$order->setState(Order::STATE_COMPLETE);
					$order->setStatus(Order::STATE_COMPLETE);
					$order->save();
				}
			}
		}
	}
}