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

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Notify extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $_moneticoPayment;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \NDP\Monetico\Model\Monetico $moneticoPayment
    ) {
        $this->_moneticoPayment = $moneticoPayment;
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
        } else {
            $data = $this->getRequest()->getQuery();
        }
        $this->_moneticoPayment->executeNotifyRequest($data);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
