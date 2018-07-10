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
    protected $_moneticoHelper;
    protected $_urlBuilder;
    protected $_transaction;
    protected $_invoiceService;

    protected $_testMode;
    protected $_eptNumber;
    protected $_apiUrl;
    protected $_key;
    protected $_companyCode;
    protected $_storeName;
    protected $_testModeConfigSuffix;
    protected $_expressPayment;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Framework\UrlInterface $urlBuilder,
        \NDP\Monetico\Helper\Data $moneticoHelper,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [])
    {
        $this->_orderInterface = $orderInterface;
        $this->_moneticoHelper = $moneticoHelper;
        $this->_urlBuilder = $urlBuilder;
        $this->_transaction = $transaction;
        $this->_invoiceService = $invoiceService;

        parent::__construct($context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);
    }

    public function executeNotifyRequest($params)
    {
        // Send reception to bank
        $calcMac = $this->checkBackData($params);
        $getMAC = isset($params['MAC']) ? strtolower($params['MAC']) : '';
        $correctHash = $getMAC == $calcMac;
        $this->_moneticoHelper->getApiResponse($correctHash);

        //$this->_logger->error("Monetico - params - " . print_r($params, true));
        //$this->_logger->error("Monetico - calc MAC - " . $calcMac);

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
                if ($savedCc == "1" || $savedCc == "0") {
                    $this->_moneticoHelper->setCustomerCcSaved($order->getCustomerId(), $savedCc);
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

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setStatus($order->getConfig()->getStateDefaultStatus($order->getState()));
                $order->addStatusToHistory($order->getStatus(), $this->getSuccessfulPaymentMessage($params));

                if ($order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                    $order->addStatusHistoryComment(__('Invoice %1 created', $invoice->getIncrementId()))->setIsCustomerNotified(false)->save();
                }

                $order->save();
            }

        } catch (\Exception $e) {
            $order->addStatusHistoryComment(
                $this->getRefusedPaymentMessage($params, 'Exception: ' . $e->getMessage())
            );
            $order->save();
        }
    }


    public function getPostData($orderId, $saveCc = "0")
    {
        $order = $this->getOrder($orderId);

        $reference = $order->getIncrementId();
        $email = $order->getCustomerEmail();

        // Amount : format  "xxxxx.yy" (no spaces)
        $amount = round($order->getData('base_grand_total'), 2);

        // Currency : ISO 4217 compliant
        $currency = "EUR";

        $freeText = (string)__('Payment for %1 order', $this->getStoreName());
        $date = date("d/m/Y:H:i:s");
        $language = "FR";

        if ($this->getExpressPayment()) {
            $options = "aliascb=client" . $order->getCustomerId(); // Monetico Express payment

            $savedCc = $this->_moneticoHelper->isCustomerCcSaved($order->getCustomerId());
            if (!$savedCc || $saveCc == "0") {
                $options = $options . "&forcesaisiecb=1"; // Force saisie
            }
        }
        else {
            $options = "forcesaisiecb=1"; // Force saisie
        }

        $postFields = implode('*', [
            $this->getEptNumber(),
            $date,
            $amount . $currency,
            $reference,
            $freeText,
            self::MONETICO_VERSION,
            $language,
            $this->getCompanyCode(),
            $email,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $options]);

        // HMAC computation
        $hmac = $this->_moneticoHelper->computeHmac($postFields, $this->getKey());

        $postData = array(
            'version' => self::MONETICO_VERSION,
            'TPE' => $this->getEptNumber(),
            'date' => $date,
            'montant' => $amount . $currency,
            'reference' => $reference,
            'MAC' => $hmac,
            'url_retour' => $this->_urlBuilder->getUrl("/"),
            'url_retour_ok' => $this->_urlBuilder->getUrl(self::MONETICO_URLOK),
            'url_retour_err' => $this->_urlBuilder->getUrl(self::MONETICO_URLKO),
            'lgue' => $language,
            'societe' => $this->getCompanyCode(),
            'texte-libre' => $this->_moneticoHelper->htmlEncode($freeText),
            'mail' => $email,
            'options' => $options
        );

        return $postData;
    }

    public function checkBackData($data)
    {
        if ($data == null) {
            return null;
        }

        // Message Authentication
        $backFields = implode('*', [
                $this->getEptNumber(),
                $data["date"],
                $data['montant'],
                $data['reference'],
                $data['texte-libre'],
                self::MONETICO_VERSION,
                $data['code-retour'],
                $data['cvx'],
                $data['vld'],
                $data['brand'],
                $data['status3ds'],
                $data['numauto'],
                $data['motifrefus'],
                $data['originecb'],
                $data['bincb'],
                $data['hpancb'],
                $data['ipclient'],
                $data['originetr'],
                $data['veres'],
                $data['pares']
            ]) . '*';

        return $this->_moneticoHelper->computeHmac($backFields, $this->getKey());
    }


    public function getSuccessfulPaymentMessage($postData)
    {
        $msg = __('Payment accepted by Monetico');
        if (array_key_exists('numauto', $postData)) {
            $msg .= "<br/>" . __('Number of authorization: %1', $postData['numauto']);
            $msg .= "<br/>" . __('Was the visual cryptogram seized: %1', $postData['cvx']);
            $msg .= "<br/>" . __('Validity of the card: %1', $postData['vld']);
            $msg .= "<br/>" . __('Type of the card: %1', $postData['brand']);
        }
        return $msg;
    }

    public function getRefusedPaymentMessage($postData, $additionalText)
    {
        $msg = __('Payment refused by Monetico. ' . $additionalText);
        if (array_key_exists('motifrefus', $postData)) {
            $msg .= "<br/>" . __('Motive for refusal: %1', $postData['motifrefus']);
            $msg .= "<br/>" . __('Was the visual cryptogram seized: %1', $postData['cvx']);
            $msg .= "<br/>" . __('Validity of the card: %1', $postData['vld']);
            $msg .= "<br/>" . __('Type of the card: %1', $postData['brand']);
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
}