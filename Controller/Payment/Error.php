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

class Error extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->_checkoutSession = $checkoutSession;

        parent::__construct($context);
    }

    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        if ($order->getId()) {
//            $this->_checkoutSession
//                ->setLastOrderId($order->getId())
//                ->setLastRealOrderId($order->getIncrementId())
//                ->setLastOrderStatus($order->getStatus())
//                ->setLastSuccessQuoteId($order->getQuoteId());

//            if ($order->canCancel()) {
//                $order->cancel();
//            }

            $order->addStatusHistoryComment( __('Customer returned from Monetico. Payment Failure.'));
            $order->save();
        }

        $this->_redirect('checkout/onepage/failure');
    }

}
