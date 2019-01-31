<?php
namespace TranzzoPayment\Tranzzo\Controller\Adminhtml\Confirm;

use \Magento\Backend\App\Action;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Sales\Model\OrderFactory;
use \TranzzoPayment\Tranzzo\Model\Tranzzo;

class Index extends Action
{
	protected $_context;
	protected $_resultPageFactory;
	protected $_orderFactory;
	protected $_paymentModel;

	protected $messageManager;

	public function __construct(
		Action\Context $context,
		PageFactory $resultPageFactory,
		OrderFactory $orderFactory,
		Tranzzo $paymentModel
	){
		parent::__construct($context);

		$this->_context = $context;
		$this->_resultPageFactory = $resultPageFactory;
		$this->_orderFactory = $orderFactory;
		$this->_paymentModel = $paymentModel;

		$this->messageManager = $context->getMessageManager();

	}

	public function execute()
	{
		$params = $this->_request->getParams();

		var_dump($params);

		if(isset($params['order_id'])) {
			$result = $this->_paymentModel->confirmCapture($params, $this->_orderFactory);


//			$this->messageManager->addSuccess(__('Payment Capture.'));
//			$this->messageManager->addError(json_encode($result));

//			$this->messageManager->addMessage($result);

//			return $this->resultRedirectFactory->create()->setPath(
//				'sales/order/view',
//				[
//					'order_id' => $params['order_id']
//				]
//			);

		}
	}
}