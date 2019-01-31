<?php
namespace TranzzoPayment\Tranzzo\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

use Magento\Sales\Model\Order\Payment\Transaction;

//use TranzzoPayment\Tranzzo\Model\Api as ApiTranzzo;
use TranzzoPayment\Tranzzo\Model\Api\Payment;
use TranzzoPayment\Tranzzo\Model\Api\InfoProduct;
use TranzzoPayment\Tranzzo\Model\Api\ResponseParams;

/**
 * Pay In Store payment method model
 */
class Tranzzo extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    const CODE = 'tranzzo';
    protected $_code = self::CODE;

    protected $_isGateway               = true;

    protected $_canAuthorize            = true;

    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canCaptureOnce          = true;

    protected $_canVoid                 = true;

    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;

    protected $_transactionBuilder;
    protected $_invoiceService;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        Transaction\BuilderInterface $builderInterface,
        InvoiceService $invoiceService,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_context = $context;
        $this->_transactionBuilder = $builderInterface;
        $this->_invoiceService = $invoiceService;

        $messageManager = $this->getObjectManager()->create('Magento\Framework\App\Action\Context');
        $this->messageManager = $messageManager->getMessageManager();

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function createPayment(Order $order)
    {
        $tranzzo = $this->getApiTranzzo();

        $tranzzo->setOrderId($order->getIncrementId());
        $tranzzo->setAmount($order->getGrandTotal());
        $tranzzo->setCurrency($order->getOrderCurrencyCode());
        $tranzzo->setDescription("#{$order->getIncrementId()}");

        $tranzzo->setCustomerId($order->getCustomerId());
        $tranzzo->setCustomerFirstName($order->getCustomerFirstname());
        $tranzzo->setCustomerLastName($order->getCustomerLastname());

        $tranzzo->setCustomerEmail($order->getCustomerEmail());
        $tranzzo->setCustomerPhone();

        $orderItems = $order->getAllVisibleItems();
        if(!empty($orderItems)) {
            foreach ($orderItems as $orderItem) {
                $infoProduct = new InfoProduct();

                $infoProduct->setProductId($orderItem->getProductId());
                $infoProduct->setProductName($orderItem->getName());
                $infoProduct->setCurrency($order->getOrderCurrencyCode());
                $infoProduct->setAmount($orderItem->getRowTotal());
                $infoProduct->setQuantity($orderItem->getQtyOrdered());

                $tranzzo->addProduct($infoProduct->get());
            }
        }
        else{
            $tranzzo->setProducts([]);
        }

        if($order->getCustomerId())
            $tranzzo->setResultUrl($this->getBaseUrl() . "sales/order/view/order_id/{$order->getId()}/");
        else
            $tranzzo->setResultUrl($this->getBaseUrl() . 'checkout/onepage/success/');

        if($this->getConfigData('payment_action') == Payment::P_METHOD_AUTH) {
            $tranzzo->setServerUrl($this->getUrlCallbackAuth());
            $response = $tranzzo->createPaymentAuth();
        } else {
            $tranzzo->setServerUrl($this->getUrlCallbackPurchase());
            $response = $tranzzo->createPaymentPurchase();
        }

        if(!empty($response['redirect_url'])) {
            return [ 'redirect' => $response['redirect_url'] ];
        }
        else
            return [ 'response' => $response['message'] . ' - ' . implode(', ', $response['args']) ];
    }

    public function processPurchase($response, OrderFactory $orderFactory)
    {
        if(empty($response['data']) || empty($response['signature'])) die('Wrong data!');

        $data = $response['data'];
        $signature = $response['signature'];

        $response = new ResponseParams(Payment::parseDataResponse($data));
        Payment::writeLog(['callback_purchase' => $response->getData()]);

        $order_id = (int)$response->getProvOrderId();
        $order = $orderFactory->create()->loadByIncrementId($order_id);

        $payment = $order->getPayment();
        if($payment->getMethod() == self::CODE) {
            $tranzzo = $this->getApiTranzzo();

            if($tranzzo -> validateSignature($data, $signature)) {

                if($response->getStatus() == Payment::STATUS_INIT || $response->getStatus() == Payment::STATUS_PROCESSING
                    || $response->getStatus() == Payment::STATUS_PENDING){
                    return;
                }

                $amount_payment = Payment::amountToDouble($response->getAmount());
                $amount_order = Payment::amountToDouble($order->getGrandTotal());

                if (($response->getResponseCode() == 1000 || $response->getStatus() == Payment::STATUS_SUCCESS)
                    && ($amount_payment >= $amount_order)) {

                    $transactionId = $this->generateTransId($response->getOrderId());
                    $payment->setLastTransId($transactionId);
                    $payment->setAdditionalInformation([Transaction::RAW_DETAILS => $response->getData()]);

                    $transaction = $this->_transactionBuilder->setPayment($payment)
                        ->setOrder($order)
                        ->setTransactionId($transactionId)
                        ->setAdditionalInformation(
                            [Transaction::RAW_DETAILS => $payment->getAdditionalInformation()]
                        )
                        ->setFailSafe(true)
                        ->build(Transaction::TYPE_CAPTURE);

                    $message = __('The Captured amount is %1.', $amount_payment);
                    $payment->addTransactionCommentsToOrder($transaction, $message);
                    $payment->setSkipOrderProcessing(true);
                    $payment->setParentTransactionId(null);

                    if ($order->getState() == Order::STATE_CANCELED) {
                        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    }

                    $payment->save();
                    $transaction->save();
                    $order->save();
                    if($order->canInvoice()) {
                        $invoice = $this->_invoiceService->prepareInvoice($order);
                        $invoice->setTransactionId($payment->getTransactionId())
                            ->addComment("Invoice created.")
                            ->setRequestedCaptureCase(Order\Invoice::CAPTURE_ONLINE);
                        $invoice->setGrandTotal($amount_order);
                        $invoice->setBaseGrandTotal($amount_order);
                        $invoice->register();
                        $invoice->save();

                        // Save the invoice to the order
                        $order->addStatusHistoryComment(__('Created Invoice #%1.', $invoice->getId()))
                            ->setIsCustomerNotified(true);

                        $order->save();
                    }
                } else {
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();
                }
            }
        }
    }

    public function processAuth($resParams, OrderFactory $orderFactory)
    {
        if(empty($resParams['data']) || empty($resParams['signature']))
            die('Wrong data!');

        $data = $resParams['data'];
        $signature = $resParams['signature'];

        $response = new ResponseParams(Payment::parseDataResponse($data));

        Payment::writeLog([
            'response' => $resParams,
            'callback_auth' => $response->getData()
        ], '', 'callback_auth');

        $order_id = (int)$response->getProvOrderId();

        $order = $orderFactory->create()->loadByIncrementId($order_id);
        $payment = $order->getPayment();
        if($payment->getMethod() == self::CODE && !empty($order) && !empty($response->getData())) {

            $tranzzo = $this->getApiTranzzo();

            if($tranzzo->validateSignature($data, $signature) && $response->getMethod() == Payment::P_METHOD_AUTH) {

                if($response->getStatus() == Payment::STATUS_INIT || $response->getStatus() == Payment::STATUS_PROCESSING
                    || $response->getStatus() == Payment::STATUS_PENDING){
                    return;
                }

                $amount_payment = Payment::amountToDouble($response->getAmount());
                $amount_order = Payment::amountToDouble($order->getGrandTotal());

                if (($response->getResponseCode() == 1002 || $response->getStatus() == Payment::STATUS_SUCCESS)
                    && ($amount_payment >= $amount_order)) {

                    if($order->canInvoice()) {
                        $addInfo = array_merge($response->getData(),
                            ['original_amount_order' => $order->getGrandTotal()]
                        );
                        $payment->setAdditionalInformation([Transaction::RAW_DETAILS => $addInfo]);
                        $payment->setAmountAuthorized($order->getGrandTotal());
                        $payment->setIsTransactionClosed(0);

                        $transactionId = $this->generateTransId($response->getOrderId());
                        $transaction = $this->_transactionBuilder->setPayment($payment)
                            ->setOrder($order)
                            ->setTransactionId($transactionId)
                            ->setAdditionalInformation([Transaction::RAW_DETAILS => $response->getData()])
                            ->addAdditionalInformation('original_amount_order', $order->getGrandTotal())
                            ->setFailSafe(true)
                            ->build(Transaction::TYPE_AUTH);

                        $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
                        // Add transaction to payment
                        $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $formatedPrice));
                        $payment->setLastTransId($transactionId);
                        // Save payment, transaction
                        $payment->save();
                        $transaction->save();


                        if ($order->getState() == Order::STATE_CANCELED) {
                            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                            $order->save();
                        }
                    }

                } else {
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();
                }
            }
        }
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // event sales_order_invoice_pay $invoice

        if($payment->getMethod() == self::CODE) {
            $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $data = new ResponseParams($dataTransaction);

            $transaction = $this->getObjTransaction($payment->getLastTransId());
            if($transaction->getTxnType() != Transaction::TYPE_AUTH){
                $message = 'Invalid last TRANZZO Transaction!!!';

                throw new \Exception($message);
            }

            if(!empty($data->getOrderId())) {
                $tranzzo = $this->getApiTranzzo();

                $tranzzo->setOrderId($data->getOrderId());
                $tranzzo->setOrderCurrency($data->getCurrency());
                $tranzzo->setOrderAmount($data->getAmount());
                $tranzzo->setServerUrl($this->getUrlCallbackCapture());

                if (Payment::amountToDouble($amount) != Payment::amountToDouble($payment->getAmountAuthorized())) {
                    $tranzzo->setAmountPartialCapture($amount);
                }

                $result = $tranzzo->createCapture();

                Payment::writeLog($result, '', 'capture_after.log');

                $response = new ResponseParams($result);
                if ($response->getStatus() != Payment::STATUS_SUCCESS){
                    $order = $payment->getOrder();

                    $message = 'TRANZZO capture failed!!! ' . $response->getMessage() . ' Status - ' . $response->getStatus();

                    $order->addStatusHistoryComment($message);
                    $order->save();

                    throw new \Exception($message);
//                    throw new \Magento\Framework\Exception\LocalizedException(__($message));
                } else {
                    $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
                    $dataTransaction = array_merge($dataTransaction, ['capture' => $response->getData()]);
                    $payment->setAdditionalInformation(Transaction::RAW_DETAILS, $dataTransaction);
                }
            }
        }

        return $this;
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        // sales_order_payment_void', ['payment' => $this, 'invoice' => $document]

        if($payment->getMethod() == self::CODE) {
            $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $data = new ResponseParams($dataTransaction);
            if(!empty($data->getOrderId())) {
                $tranzzo = $this->getApiTranzzo();

                $tranzzo->setOrderId($data->getOrderId());
                $tranzzo->setOrderCurrency($data->getCurrency());
                $tranzzo->setOrderAmount($data->getAmount());

                $result = $tranzzo->createVoid();

                Payment::writeLog($result, '', 'void_after.log');

                $response = new ResponseParams($result);
                if ($response->getStatus() != Payment::STATUS_SUCCESS) {
                    $message = 'TRANZZO voided failed!!! ' . $response->getMessage();

                    $order = $payment->getOrder();
                    $order->addStatusHistoryComment($message);
                    $order->save();

                    $this->setToMessageManagerError($message);
                    throw new \Exception();
//                    throw new \Magento\Framework\Exception\LocalizedException(__($message));
                } else{
                    $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
                    $dataTransaction = array_merge($dataTransaction, ['void' => $response->getData()]);
                    $payment->setAdditionalInformation(Transaction::RAW_DETAILS, $dataTransaction);
                }
            }
        }

        return $this;
    }

	/**
     * Refund specified amount for payment
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // sales_order_payment_refund ['payment' => $this, 'creditmemo' => $creditmemo]

        if($payment->getMethod() == self::CODE) {
            $transaction = $this->getObjTransaction($payment->getLastTransId());
            if($transaction->getTxnType() != Transaction::TYPE_CAPTURE){
                $message = 'Not found captured TRANZZO Transaction!!!';

                throw new \Exception($message);
            }

            $dataTransaction = $this->getInfoTransaction($payment->getLastTransId());
            $data = new ResponseParams($dataTransaction);
            if(!empty($data->getOrderId())) {
                $tranzzo = $this->getApiTranzzo();

                $tranzzo->setOrderId($data->getOrderId());
                $tranzzo->setOrderCurrency($data->getCurrency());
                $tranzzo->setOrderAmount($data->getAmount());
                $tranzzo->setServerUrl($this->getUrlCallbackRefund());

                $order = $payment->getOrder();
                if (Payment::amountToDouble($amount) != Payment::amountToDouble($data->getAmount())) {
                    $tranzzo->setAmountPartialRefund($amount);
                }

                $result = $tranzzo->createRefund();

                Payment::writeLog($result, '', 'refund_after.log');

                $response = new ResponseParams($result);
                if ($response->getStatus() != Payment::STATUS_SUCCESS){
                    $message = 'TRANZZO refund failed!!! ' . $response->getMessage() . ' Status - ' . $response->getStatus();

                    $order->addStatusHistoryComment($message);
                    $order->save();

                    throw new \Exception($message);
                } else {
                    $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
                    $dataTransaction = array_merge($dataTransaction, ['refund' => $response->getData()]);
                    $payment->setAdditionalInformation(Transaction::RAW_DETAILS, $dataTransaction);
                }
            }
        }

        return $this;
    }

    public function processCapture($resParams, OrderFactory $orderFactory)
    {
        Payment::writeLog($resParams, '', 'callback_capture');
        if(empty($resParams['data']) || empty($resParams['signature'])) die('Wrong data!');

        $data = $resParams['data'];
        $signature = $resParams['signature'];

        $response = new ResponseParams(Payment::parseDataResponse($data));
        Payment::writeLog(['callback_capture' => $response->getData()], '', 'callback_capture');

        $tranzzo = $this->getApiTranzzo();
        if ($tranzzo->validateSignature($data, $signature)) {
            if ($response->getMethod() == Payment::U_METHOD_CAPTURE) {
                $firstDataTransaction = new ResponseParams(
                    $this->getInfoTransaction($this->generateTransId($response->getOrderId()))
                );
                $order_id = (int)$firstDataTransaction->getProvOrderId();
                $order = $orderFactory->create()->loadByIncrementId($order_id);
                $payment = $order->getPayment();

                if($payment->getMethod() == self::CODE) {
                    $transactionId = $payment->getLastTransId();
                    $transaction = $this->getObjTransaction($transactionId);
                    if($transaction->getTxnType() == Transaction::TYPE_CAPTURE) {
                        $addInfo = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
                        $addInfo = !empty($addInfo) ? array_merge($addInfo, $response->getData()) : $response->getData();
                        $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $addInfo);
                        $transaction->save();
                    } else {
                        $transactionId = $transactionId . '-capture';
                        $this->createTransaction($transactionId, Transaction::TYPE_CAPTURE, $response->getData(), $order);
                    }
                }
            }
        }
    }

    public function processRefund($resParams, OrderFactory $orderFactory)
    {
        Payment::writeLog($resParams, '', 'callback_refund');
        if(empty($resParams['data']) || empty($resParams['signature'])) die('Wrong data!');

        $data = $resParams['data'];
        $signature = $resParams['signature'];

        $response = new ResponseParams(Payment::parseDataResponse($data));
        Payment::writeLog(['callback_refund' => $response->getData()], '', 'callback_refund');

        $tranzzo = $this->getApiTranzzo();
        if ($tranzzo->validateSignature($data, $signature)) {
            if ($response->getMethod() == Payment::U_METHOD_REFUND) {
                $firstDataTransaction = new ResponseParams(
                    $this->getInfoTransaction($this->generateTransId($response->getOrderId()))
                );
                $order_id = (int)$firstDataTransaction->getProvOrderId();
                $order = $orderFactory->create()->loadByIncrementId($order_id);
                $payment = $order->getPayment();

                if($payment->getMethod() == self::CODE) {
                    $transactionId = $payment->getLastTransId();
                    $transaction = $this->getObjTransaction($transactionId);
                    if ($transaction->getTxnType() == Transaction::TYPE_REFUND) {
                        $addInfo = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
                        $addInfo = !empty($addInfo) ? array_merge($addInfo, $response->getData()) : $response->getData();
                        $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $addInfo);
                        $transaction->save();
                    } else {
                        $transactionId = $transactionId . '-refund';
                        $this->createTransaction($transactionId, Transaction::TYPE_REFUND, $response->getData(), $order);
                    }

                    if($response->getStatus() == Payment::STATUS_SUCCESS && $order->getState() != Order::STATE_CLOSED) {
                        $order->setState(Order::STATE_CLOSED);
                        $order->setStatus(Order::STATE_CLOSED);
                        $order->save();
                    }
                }
            }
        }
    }

    protected function generateTransId($id)
    {
        return $id . '-' . self::CODE;
    }

    protected function createTransaction($transactionId, $type, $response, Order $order, $isClosed = true)
    {
        $transaction = $this->_transactionBuilder->setPayment($order->getPayment()->setIsTransactionClosed($isClosed))
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => $response])
            ->setFailSafe(true)
            ->build($type);
        $transaction->save();
    }

    public function getObjTransaction($txn_id = null)
    {
        $transaction = $this->getObjectManager()->create(Transaction::class)
            ->load($txn_id, Transaction::TXN_ID);

        return ($transaction instanceof Transaction)? $transaction : null;
    }

    public function getInfoTransaction($txn_id = null)
    {
        return $this->getObjTransaction($txn_id)->getAdditionalInformation(Transaction::RAW_DETAILS);
    }

    public function getApiTranzzo()
    {
        return new Payment(
            $this->getConfigData('POS_ID'),
            $this->getConfigData('API_KEY'),
            $this->getConfigData('API_SECRET'),
            $this->getConfigData('ENDPOINTS_KEY')
        );
    }

    public function getBaseUrl()
    {
        $storeManager = $this->getObjectManager()->get('\Magento\Store\Model\StoreManagerInterface');

        return $storeManager->getStore()->getBaseUrl();
    }

    public function getUrlCallbackPurchase()
    {
        return $this->getBaseUrl() . 'tranzzo/callback/purchase';
    }

    public function getUrlCallbackAuth()
    {
        return $this->getBaseUrl() . 'tranzzo/callback/auth';
    }

    public function getUrlCallbackCapture()
    {
        return $this->getBaseUrl() . 'tranzzo/callback/capture';
    }

    public function getUrlCallbackRefund()
    {
        return $this->getBaseUrl() . 'tranzzo/callback/refund';
    }

    public function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function getBackendUser()
    {
        return $this->getObjectManager()->create('\Magento\Backend\Model\Auth\Session')->getUser();
    }

    protected function getLogger()
    {
        return $this->getContext()->getLogger();
    }

    protected function getContext()
    {
        return $this->_context;
    }

    /**
     * Get Instance of Magento global Message Manager
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->messageManager;
    }

    protected function createTypeMessage($text = '', $isSuccess = false)
    {
        $class = $isSuccess ? 'Magento\Framework\Message\Success' : 'Magento\Framework\Message\Error';
        return $this->getObjectManager()->create($class, ['text' => $text]);
    }

    protected function createSuccessMessage($text = '')
    {
        return $this->getObjectManager()->create('Magento\Framework\Message\Success', ['text' => $text]);
    }

    protected function createErrorMessage($text = '')
    {
        return $this->getObjectManager()->create('Magento\Framework\Message\Error', ['text' => $text]);
    }

    protected function setToMessageManagerError($message)
    {
        $this->getMessageManager()->addMessage($this->createErrorMessage($message));
    }

    protected function setToMessageManagerSuccess($message)
    {
        $this->getMessageManager()->addMessage($this->createSuccessMessage($message));
    }
}
