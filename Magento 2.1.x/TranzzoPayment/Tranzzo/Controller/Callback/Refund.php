<?php
namespace TranzzoPayment\Tranzzo\Controller\Callback;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Request\Http;
use \Magento\Sales\Model\OrderFactory;
use \TranzzoPayment\Tranzzo\Model\Tranzzo;

class Refund extends Action
{
	protected $_context;
	protected $_request;
	protected $_orderFactory;
	protected $_paymentModel;

	public function __construct(
		Context $context,
		Http $request,
		OrderFactory $orderFactory,
		Tranzzo $paymentModel
	){
		$this->_context = $context;
		$this->_request = $request;
		$this->_orderFactory = $orderFactory;
		$this->_paymentModel = $paymentModel;

		parent::__construct($context);
	}

	public function execute()
	{
		$this->_paymentModel->processRefund($this->_request->getParams(), $this->_orderFactory);
	}

	protected function getOrderFactory()
	{
		return $this->_orderFactory;
	}

	protected function getContext()
	{
		return $this->_context;
	}

	protected function getObjectManager()
	{
		return $this->_objectManager;
	}
}