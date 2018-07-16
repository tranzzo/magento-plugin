<?php

namespace TranzzoPayment\Tranzzo\Block;

use \Magento\Framework\View\Element\Template;

class Redirect extends Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;

    protected $_template = 'html/form.phtml';

    protected $_modelPayment;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \TranzzoPayment\Tranzzo\Model\Tranzzo $modelPayment,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_orderConfig = $orderConfig;
        $this->httpContext = $httpContext;
        $this->_modelPayment = $modelPayment;
    }

    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    public function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function msgFail()
    {
        return __("There was an error creating the payment");
    }
}
