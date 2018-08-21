<?php
namespace TranzzoPayment\Tranzzo\Model;

use Magento\Payment\Model\Method\AbstractMethod;
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
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

use TranzzoPayment\Tranzzo\Model\Api\Payment;
use TranzzoPayment\Tranzzo\Model\Api\InfoProduct;

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
    protected $_isGateway                   = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
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
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $builderInterface,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_transactionBuilder = $builderInterface;
        $this->_invoiceService = $invoiceService;
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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $baseurl = $storeManager->getStore()->getBaseUrl();

        $tranzzo = new Payment(
            $this->getConfigData('POS_ID'),
            $this->getConfigData('API_KEY'),
            $this->getConfigData('API_SECRET'),
            $this->getConfigData('ENDPOINTS_KEY')
        );

        $tranzzo->setOrderId($order->getIncrementId());
        $tranzzo->setAmount($order->getGrandTotal());
        $tranzzo->setCurrency($order->getOrderCurrencyCode());
        $tranzzo->setDescription("#{$order->getIncrementId()}");

        $tranzzo->setServerUrl($baseurl . 'tranzzo/checkout/callback');
        $tranzzo->setResultUrl($baseurl . "sales/order/view/order_id/{$order->getId()}/");
//        $tranzzo->setResultUrl($baseurl . 'checkout/onepage/success/');

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
//                $infoProduct->setProductURL(  );
                $tranzzo->addProduct($infoProduct->get());
            }
        }
        else{
            $tranzzo->setProducts([]);
        }

        $response = $tranzzo->createPaymentHosted();

        if(!empty($response['redirect_url'])) {
            return [ 'redirect' => $response['redirect_url'] ];
        }
        else
            return [ 'response' => $response['message'] . ' - ' . implode(', ', $response['args']) ];
    }

    public function processCallback($response, OrderFactory $orderFactory)
    {
        if(empty($response['data']) || empty($response['signature'])) die('Wrong data!');
        $data = $response['data'];
        $signature = $response['signature'];

        $data_response = Payment::parseDataResponse($data);
        Payment::writeLog(['callback'=>$data_response]);
        $order_id = (int)$data_response[Payment::P_RES_PROV_ORDER];
        $order = $orderFactory->create()->loadByIncrementId($order_id);

        if($order->getPayment()->getMethod() == self::CODE) {

            $tranzzo = new Payment(
                $this->getConfigData('POS_ID'),
                $this->getConfigData('API_KEY'),
                $this->getConfigData('API_SECRET'),
                $this->getConfigData('ENDPOINTS_KEY')
            );

            if($tranzzo -> validateSignature($data, $signature)) {

                $amount_payment = Payment::amountToDouble($data_response[Payment::P_RES_AMOUNT]);
                $amount_order = Payment::amountToDouble($order->getGrandTotal());

                $payment = $order->getPayment();
                if ($data_response[Payment::P_RES_RESP_CODE] == 1000 && ($amount_payment >= $amount_order)) {

                    $trans_id = $data_response[Payment::P_RES_ORDER];
                    $payment->setLastTransId($trans_id);
                    $payment->setTransactionId($trans_id);
                    $payment->setAdditionalInformation(
                      [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => $data_response]
                    );

                    $message = __('The Captured amount is %1.', $amount_payment);
//get the object of builder class Magento\Sales\Model\Order\Payment\Transaction\Builder
                    $trans = $this->_transactionBuilder;
                    $transaction = $trans->setPayment($payment)
                      ->setOrder($order)
                      ->setTransactionId($trans_id)
                      ->setAdditionalInformation(
                        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$payment->getAdditionalInformation()]
                      )
                      ->setFailSafe(true)
                      //build method creates the transaction and returns the object
                      ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

                    $payment->addTransactionCommentsToOrder(
                      $transaction,
                      $message
                    );
                    if ($order->getState() == Order::STATE_CANCELED) {
                        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    }
                    $payment->setSkipOrderProcessing(true);
                    $payment->setParentTransactionId(null);

                    $payment->save();
                    $order->save();
                    $transaction->save();
                    if($order->canInvoice()) {
                        $invoice = $this->_invoiceService->prepareInvoice($order);
                        $invoice = $invoice->setTransactionId($payment->getTransactionId())
                          ->addComment("Invoice created.")
                          ->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                        $invoice->setGrandTotal($amount_order);
                        $invoice->setBaseGrandTotal($amount_order);
                        $invoice->register()
                          ->pay();
                        $invoice->save();
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                        // Save the invoice to the order
                        $transaction = $objectManager->create('Magento\Framework\DB\Transaction')
                          ->addObject($invoice)
                          ->addObject($invoice->getOrder());
                        $transaction->save();

                        $order->addStatusHistoryComment(
                          __('Invoice #%1.', $invoice->getId())
                        )
                          ->setIsCustomerNotified(true);

                        $order->save();
                    }
                } elseif ($data_response['method'] == 'refund') {
                    $order->addStatusHistoryComment(
                      'Refunded ' . $data_response['status']
                    )
                      ->setIsCustomerNotified(FALSE);

                    $order->save();
                } else {
                    $payment->setTransactionId($order_id)->setIsTransactionClosed(0);
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();
                }
            }
        }
    }
    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $baseurl = $storeManager->getStore()->getBaseUrl();
        $data_response = $payment->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);
        $tranzzo = new Payment(
            $this->getConfigData('POS_ID'),
            $this->getConfigData('API_KEY'),
            $this->getConfigData('API_SECRET'),
            $this->getConfigData('ENDPOINTS_KEY')
        );
        $nvp_data = [
          'order_id' => $data_response['order_id'],
          'order_amount' => Payment::amountToDouble($data_response[Payment::P_RES_AMOUNT]),
          'order_currency' => $data_response[Payment::P_RES_CURRENCY],
          'refund_date' => date('Y-m-d H:i:s', time()),
          'amount' => Payment::amountToDouble($amount),
          'server_url' =>  $baseurl . 'tranzzo/checkout/callback',
        ];
        try {
            $tranzzo_response = $tranzzo->createRefund($nvp_data);
            if ($tranzzo_response['status'] != 'success') {
                $message = $tranzzo_response['message'];
                throw new \Exception($message);
            }

        } catch (\Exception $e) {
            $this->getLogger()->error(
              $e->getMessage()
            );

            $this->getMessageManager()->addError(
              $e->getMessage()
            );

            $this->getModuleHelper()->maskException($e);
        }

        return $this;
    }
}
