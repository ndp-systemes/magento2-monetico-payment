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

class Notify extends \Magento\Framework\App\Action\Action
{
    protected $_moneticoPayment;
    protected $_helper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \NDP\Monetico\Model\Monetico $moneticoPayment,
        \NDP\Monetico\Helper\Data $helper
    ) {
        $this->_moneticoPayment = $moneticoPayment;
        $this->_helper = $helper;

        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
        } else if ($this->getRequest()->isGet()) {
            $data = $this->getRequest()->getQuery();
        }

        $this->_moneticoPayment->executeNotifyRequest($data);
    }
}
