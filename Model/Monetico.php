<?php
/**
 * NDP_Monetico extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License ("OSL") v. 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category       NDP
 * @package        NDP_Monetico
 * @copyright      Copyright (c) 2017
 * @author         NDP Systèmes
 * @license        Open Software License ("OSL") v. 3.0
 */

namespace NDP\Monetico\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\InvoiceDocumentFactory;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\PaymentAdapterInterface;
use Magento\Sales\Model\Order\Validation\InvoiceOrderInterface as InvoiceOrderValidator;

class Monetico extends \Magento\Payment\Model\Method\AbstractMethod
{
    const MONETICO_VERSION = "3.0";
    const MONETICO_URLOK = "monetico/payment/success";
    const MONETICO_URLKO = "monetico/payment/error";
    const TEST_MODE_CONF_SUFFIX = "_test_mode";

    protected $_code = 'monetico';
    protected $_isOffline = true;
    protected $_isInitializeNeeded = true;

    protected $_orderInterface;
    protected $_orderRepository;
    protected $_invoiceOrderValidator;
    protected $_invoiceDocumentFactory;
    protected $_paymentAdapter;
    protected $_invoiceRepository;
    protected $_moneticoHelper;
    protected $_urlBuilder;
    protected $_customerRepository;

    protected $_testMode;
    protected $_eptNumber;
    protected $_apiUrl;
    protected $_key;
    protected $_companyCode;
    protected $_storeName;
    protected $_testModeConfigSuffix;
    protected $_expressPayment;
    protected $_threeDSC;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        OrderRepositoryInterface $orderRepository,
        InvoiceOrderValidator $invoiceOrderValidator,
        InvoiceRepository $invoiceRepository,
        InvoiceDocumentFactory $invoiceDocumentFactory,
        PaymentAdapterInterface $paymentAdapter,
        \Magento\Framework\UrlInterface $urlBuilder,
        \NDP\Monetico\Helper\Data $moneticoHelper,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        $this->_invoiceOrderValidator = $invoiceOrderValidator;
        $this->_invoiceDocumentFactory = $invoiceDocumentFactory;
        $this->_paymentAdapter = $paymentAdapter;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_orderRepository = $orderRepository;
        $this->_orderInterface = $orderInterface;
        $this->_moneticoHelper = $moneticoHelper;
        $this->_urlBuilder = $urlBuilder;
        $this->_customerRepository = $customerRepository;

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

    public function executeNotifyRequest($params)
    {
        $getMAC = isset($params['MAC']) ? strtolower($params['MAC']) : '';

        // Send reception to bank
        $calcMac = $this->checkBackData($params);
        $correctHash = $getMAC == $calcMac;
        $this->_moneticoHelper->getApiResponse($correctHash);

        // check MAC key
        // Stop treatment if signature is not send
        if (!$getMAC) {
            // Error no MAC signature
            $this->_logger->error("Monetico - No HMAC sent - " . $params['reference']);
            return false;
        }

        // Stop treatment if signature is not good
        if (!$correctHash) {
            $this->_logger->error("Monetico - Sent HMAC is invalid - " . $params['reference']);
            return false;
        }

        if (isset($params['reference'])) {
            $order = $this->getOrder($params['reference']);

            if ($order->getId()) {
                $savedCc = $params['cbenregistree'];
                if ($this->isCustomerSavingCc($order->getCustomerId()) && ($savedCc == "1" || $savedCc == "0")) {
                    $this->_moneticoHelper->setCustomerCcSaved($order->getCustomerId(), "1");
                }

                return $this->_processOrder($order, $params);
            }

            $this->_logger->error("Monetico - No order with this reference found - " . $params['reference']);
            return false;
        }

        $this->_logger->error("Monetico - No order reference sent");
        return false;
    }

    protected function _processOrder(\Magento\Sales\Model\Order $order, $params)
    {
        try {
            $order = $this->_orderRepository->get($order->getId());

            $continue = true;

            if (!isset($params['code-retour']) || ($params['code-retour'] != 'payetest' && $params['code-retour'] != 'paiement')) {
                $order->addStatusHistoryComment(
                    $this->getRefusedPaymentMessage($params, 'Return code is canceled')
                );
                $continue = false;
            }

            $outSum = round($order->getGrandTotal(), 2);
            if ($outSum != (float)$params["montant"]) {
                // Error amount difference
                $order->addStatusHistoryComment(
                    $this->getRefusedPaymentMessage($params, 'Amount is not valid')
                );
                $continue = false;
            }

            if (!isset($params['numauto'])) {
                $order->addStatusHistoryComment(
                    $this->getRefusedPaymentMessage($params, 'NumAuto is not send')
                );
                $continue = false;
            }

            if ($continue) {

                $order->addCommentToStatusHistory($this->getSuccessfulPaymentMessage($params))->save();

                if ($order->canInvoice()) {

                    $invoice = $this->_invoiceDocumentFactory->create($order);
                    $errorMessages = $this->_invoiceOrderValidator->validate($order, $invoice);
                    if ($errorMessages->hasMessages()) {
                        throw new \Magento\Sales\Exception\DocumentValidationException(
                            __("Invoice Validation Error(s):\n" . implode("\n", $errorMessages->getMessages()))
                        );
                    }

                    $order = $this->_paymentAdapter->pay($order, $invoice, false);
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->setStatus($order->getConfig()->getStateDefaultStatus($order->getState()));
                    $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                    $this->_invoiceRepository->save($invoice);
                    $this->_orderRepository->save($order);
                }
            }
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory(
                $this->getRefusedPaymentMessage($params, 'Exception: ' . $e->getMessage())
            )->save();
        }

    }

    public function getPostData($orderId, $saveCc = "0")
    {
        $order = $this->getOrder($orderId);

        $tpe= $this->getEptNumber();
        $reference = $order->getIncrementId();
        $societe= $this->getCompanyCode();
        $texteLibre = (string)__('Payment for %1 order', $this->getStoreName());
        $date = date("d/m/Y:H:i:s");
        $mail = $order->getCustomerEmail();
        $lgue = "FR";
        $threeDSC = $this->getThreeDSC();

        // Amount : format  "xxxxx.yy" (no spaces)
        $amount = round($order->getData('base_grand_total'), 2);
        // Currency : ISO 4217 compliant
        $currency = "EUR";
        $montant = $amount . $currency;

        $urlRetourErr= $this->_urlBuilder->getUrl(self::MONETICO_URLKO);
        $urlRetourOk= $this->_urlBuilder->getUrl(self::MONETICO_URLOK);
        $version= self::MONETICO_VERSION;

        $aliascb = '';
        $forcesaisiecb = '';
        if ($this->getExpressPayment()) {
            $savedCc = $this->_moneticoHelper->isCustomerCcSaved($order->getCustomerId());
            if ($saveCc == "1" && $savedCc) {
                $aliascb = "client" . $order->getCustomerId();
            } elseif ($saveCc == "1" && !$savedCc) {
                $aliascb = "client" . $order->getCustomerId();
                $forcesaisiecb = "1";
            }
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $rawContexteCommand = '{"billing":{                               
            "addressLine1":"' . $billingAddress->getStreetLine(1) . '",                             
            "city":"' . $billingAddress->getCity() . '",
            "postalCode":"' . $billingAddress->getPostcode() . '",  
            "country":"' . $billingAddress->getCountryId() . '"
        }';
        if ($shippingAddress) {
            $rawContexteCommand .= ',"shipping":{
                "addressLine1":"' . $billingAddress->getStreetLine(1) . '",                             
                "city":"' . $billingAddress->getCity() . '",
                "postalCode":"' . $billingAddress->getPostcode() . '",  
                "country":"' . $billingAddress->getCountryId() . '"
            }';
        }
        $rawContexteCommand .= '}';

        $utf8ContexteCommande = utf8_encode($rawContexteCommand);
        $contexteCommande = base64_encode($utf8ContexteCommande);

        $postFields = implode('*', [
            "TPE=$tpe",
            "ThreeDSecureChallenge=$threeDSC",
            "aliascb=$aliascb",
            "contexte_commande=$contexteCommande",
            "date=$date",
            "forcesaisiecb=$forcesaisiecb",
            "lgue=$lgue",
            "mail=$mail",
            "montant=$montant",
            "reference=$reference",
            "societe=$societe",
            "texte-libre=$texteLibre",
            "url_retour_err=$urlRetourErr",
            "url_retour_ok=$urlRetourOk",
            "version=$version",
        ]);

        // HMAC computation
        $hmac = $this->_moneticoHelper->computeHmac($postFields, $this->getKey());

        return [
            'TPE' => $tpe,
            'ThreeDSecureChallenge' => $threeDSC,
            'aliascb' => $aliascb,
            'contexte_commande' => $contexteCommande,
            'date' => $date,
            'forcesaisiecb' => $forcesaisiecb,
            'lgue' => $lgue,
            'mail' => $mail,
            'montant' => $montant,
            'reference' => $reference,
            'societe' => $societe,
            'texte-libre' => $texteLibre,
            'url_retour_ok' => $urlRetourOk,
            'url_retour_err' => $urlRetourErr,
            'version' => $version,
            'MAC' => $hmac,
        ];
    }

    public function checkBackData($source)
    {
        $arraySource = $source->getArrayCopy();
        // sole field to exclude from the MAC computation
        if( array_key_exists('MAC', $arraySource) )
            unset($arraySource['MAC']);

        // order by key is mandatory
        ksort($arraySource);
        // map entries to "key=value" to match the target format
        array_walk($arraySource, function(&$a, $b) { $a = "$b=$a"; });
        // join all entries using asterisk as separator
        $backFields = implode( '*', $arraySource);

        return $this->_moneticoHelper->computeHmac($backFields, $this->getKey());
    }

    public function getSuccessfulPaymentMessage($postData)
    {
        $msg = __('Payment accepted by Monetico');
        if (array_key_exists('numauto', $postData)) {
            $auth = preg_replace( "/\r|\n| /", "", base64_decode($postData['authentification']));
            $msg .= "<br/><br/>" . __('Number of authorization: %1', $postData['numauto']);
            $msg .= "<br/>" . __('Masked card number: %1', $postData['cbmasquee']);
            $msg .= "<br/>" . __('Was the visual cryptogram seized: %1', $postData['cvx']);
            $msg .= "<br/>" . __('Validity of the card: %1', $postData['vld']);
            $msg .= "<br/>" . __('Type of the card: %1', $postData['brand']);
            $msg .= "<br/>" . __('Virtual card: %1', $postData['ecard']);
            $msg .= "<br/>" . __('Authentification: %1', $auth);
        }
        return $msg;
    }

    public function getRefusedPaymentMessage($postData, $additionalText)
    {
        $msg = __('Payment refused by Monetico. ' . $additionalText);
        if (array_key_exists('motifrefus', $postData)) {
            $auth = preg_replace( "/\r|\n| /", "", base64_decode($postData['authentification']));
            $msg .= "<br/><br/>" . __('Motive for refusal: %1', $postData['motifrefus']);
            $msg .= "<br/>" . __('Masked card number: %1', $postData['cbmasquee']);
            $msg .= "<br/>" . __('Was the visual cryptogram seized: %1', $postData['cvx']);
            $msg .= "<br/>" . __('Validity of the card: %1', $postData['vld']);
            $msg .= "<br/>" . __('Type of the card: %1', $postData['brand']);
            $msg .= "<br/>" . __('Virtual card: %1', $postData['ecard']);
            $msg .= "<br/>" . __('Authentification: %1', $auth);
        }
        return $msg;
    }

    private function getOrder($orderId)
    {
        return $this->_orderInterface->loadByIncrementId($orderId);
    }

    // Getters

    public function getTestMode()
    {
        if ($this->_testMode === null) {
            $this->_testMode = $this->getConfigData('test_mode');
            $this->_testModeConfigSuffix = $this->_testMode ? $this::TEST_MODE_CONF_SUFFIX : '';
        }
        return $this->_testMode;
    }

    public function getThreeDSC()
    {
        if ($this->_threeDSC === null) {
            $this->_threeDSC = $this->getConfigData('three_dsc');
        }
        return $this->_threeDSC;
    }

    public function getEptNumber()
    {
        if ($this->_eptNumber === null) {
            $this->getTestMode();
            $this->_eptNumber = $this->getConfigData('ept_number' . $this->_testModeConfigSuffix);
        }
        return $this->_eptNumber;
    }

    public function getApiUrl()
    {
        if ($this->_apiUrl === null) {
            $this->getTestMode();
            $this->_apiUrl = $this->getConfigData('api_url' . $this->_testModeConfigSuffix);
        }
        return $this->_apiUrl;
    }

    public function getKey()
    {
        if ($this->_key === null) {
            $this->_key = $this->getConfigData('key');
        }
        return $this->_key;
    }

    public function getCompanyCode()
    {
        if ($this->_companyCode === null) {
            $this->_companyCode = $this->getConfigData('company_code');
        }
        return $this->_companyCode;
    }

    public function getExpressPayment()
    {
        if ($this->_expressPayment === null) {
            $this->_expressPayment = $this->getConfigData('express_payment');
        }
        return $this->_expressPayment;
    }

    public function getStoreName()
    {
        if ($this->_storeName === null) {
            $this->_storeName = $this->_scopeConfig->getValue('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
        return $this->_storeName;
    }

    protected function isCustomerSavingCc($customerId)
    {
        $saveCcValue = 0;

        try {
            $customer = $this->_customerRepository->getById($customerId);
            $saveCcAttribute = $customer->getCustomAttribute('save_cc');

            if ($saveCcAttribute != null) {
                $saveCcValue = $saveCcAttribute->getValue();
            }
        } catch (\Exception $e) {
            return 0;
        }
        return $saveCcValue;
    }
}
