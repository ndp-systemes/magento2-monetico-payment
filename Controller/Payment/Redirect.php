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

namespace NDP\Monetico\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action
{
    protected $_session;
    protected $_checkoutSession;
    protected $_moneticoPayment;
    protected $_resultJsonFactory;
    protected $customerRepository;
    protected $moneticoHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \NDP\Monetico\Model\Monetico $moneticoPayment,
        \Magento\Customer\Model\Session $session,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \NDP\Monetico\Helper\Data $moneticoHelper
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_moneticoPayment = $moneticoPayment;
        $this->_session = $session;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->customerRepository = $customerRepository;
        $this->moneticoHelper = $moneticoHelper;

        parent::__construct($context);
    }

    public function execute()
    {

        $order = $this->_checkoutSession->getLastRealOrder();
        if ($order->getId() && $order->getCustomerId() == $this->_session->getId()) {

            $saveCc = "0";
            $data = $this->getRequest()->getQuery();
            if ($data != null && isset($data['saveCc'])) {
                $saveCc = $data['saveCc'];
            }
            $this->saveCustomerCcConfig($order->getCustomerId(), $saveCc);

            $incrementId = $this->_checkoutSession->getLastRealOrderId();

            $data[] = [
                'fields' => $this->_moneticoPayment->getPostData($incrementId, $saveCc),
                'action' => $this->_moneticoPayment->getApiUrl()
            ];

            $order->addStatusHistoryComment("Customer redirected to Monetico");
            $order->save();

            return $this->_resultJsonFactory->create()->setData($data);
        }
    }

    protected function saveCustomerCcConfig($customerId, $saveCc) {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('save_cc', $saveCc);
            $this->customerRepository->save($customer);

            if ($saveCc == "0") {
                $this->moneticoHelper->setCustomerCcSaved($customerId, "0");
            }
        }
        catch (\Exception $e) {}
    }
}
