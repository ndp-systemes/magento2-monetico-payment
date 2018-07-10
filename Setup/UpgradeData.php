<?php

namespace NDP\Monetico\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Config;
use Magento\Customer\Model\Customer;

class UpgradeData implements UpgradeDataInterface
{
    protected $eavSetupFactory;
    protected $eavConfig;

    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        if (version_compare($context->getVersion(), "1.1.0", "<")) {

            $eavSetup->addAttribute(
                Customer::ENTITY,
                'save_cc',
                [
                    'type'         => 'int',
                    'label'        => 'Save Credit Card',
                    'input'        => 'boolean',
                    'required'     => false,
                    'visible'      => true,
                    'default'       => '0',
                    'user_defined' => false,
                    'position'     => 150,
                    'system'       => 0,
                ]
            );

            $saveCcAttribute = $this->eavConfig->getAttribute(Customer::ENTITY, 'save_cc');

            $saveCcAttribute->setData(
                'used_in_forms',
                ['customer_account_edit','adminhtml_customer']
            );


            $saveCcAttribute->save();

        }

        if (version_compare($context->getVersion(), "1.1.2", "<")) {

            $connection = $setup->getConnection();

            $connection->addColumn(
                $setup->getTable('customer_entity'),
                'saved_cc',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    'nullable' => false,
                    'default' => 0,
                    'comment' => 'Order priority questions'
                ]
            );

        }

        $setup->endSetup();
    }


}