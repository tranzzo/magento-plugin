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
    protected $_code = 'tranzzo';

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
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    )
    {

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
        $data = $response['data'];
        $signature = $response['signature'];
        if(empty($data) && empty($signature)) die('LOL! Bad Request!!!');

        $data_response = Payment::parseDataResponse($data);

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
                    $payment->setTransactionId($order_id)->setIsTransactionClosed(0);
                    $order->setStatus(Order::STATE_COMPLETE);
                    $order->setState(Order::STATE_COMPLETE);
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
}
