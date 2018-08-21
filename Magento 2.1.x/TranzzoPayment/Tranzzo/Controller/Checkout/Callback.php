<?php
namespace TranzzoPayment\Tranzzo\Controller\Checkout;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Request\Http;
use \Magento\Checkout\Model\Session;
use \Magento\Sales\Model\OrderFactory;
use \TranzzoPayment\Tranzzo\Model\Tranzzo;

class Callback extends Action
{
    protected $_context;
    protected $_request;
    protected $_checkoutSession;
    protected $_orderFactory;
    protected $_paymentModel;

    public function __construct(
        Context $context,
        Http $request,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        Tranzzo $paymentModel
    ){
        $this->_context = $context;
        $this->_request = $request;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_paymentModel = $paymentModel;

        parent::__construct($context);
    }

    public function execute()
    {
            $this->_paymentModel->processCallback($this->_request->getParams(), $this->_orderFactory);
    }

    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
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