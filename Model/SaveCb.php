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

use Magento\Checkout\Model\ConfigProviderInterface;

class SaveCb implements ConfigProviderInterface
{
    protected $session;
    protected $customerRepository;


    public function __construct(
        \Magento\Customer\Model\Session $session,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    )
    {
        $this->session = $session;
        $this->customerRepository = $customerRepository;
    }

    public function getConfig()
    {

        $customerId = $this->session->getCustomerId();
        $saveCcValue = 0;

        if ($customerId) {
            try {
                $customer = $this->customerRepository->getById($customerId);

                $saveCcAttribute = $customer->getCustomAttribute('save_cc');

                if ($saveCcAttribute != null) {
                    $saveCcValue = $saveCcAttribute->getValue();
                }
            } catch (\Exception $e) {
            }
        }

        return [
            'customer_config' => [
                'save_cc' => $saveCcValue
            ],
        ];
    }

}