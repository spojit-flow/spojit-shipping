<?php

namespace Spojit\SpojitShipping\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $tableName = $installer->getTable('spojit_carrier');
        //Check for the existence of the table
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'code',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Code'
                )
                ->addColumn(
                    'name',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Name'
                )
                ->addColumn(
                    'service',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Service'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Created At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Updated At'
                )
                ->setComment('Spojit Carrier Table');
            $installer->getConnection()->createTable($table);
        }
        $tableName = $installer->getTable('spojit_cart');
        //Check for the existence of the table
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'quote_id',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Quote ID'
                )
                ->addColumn(
                    'hash',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Address Hash'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Created At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Updated At'
                )
                ->setComment('Spojit Cart Table');
            $installer->getConnection()->createTable($table);
        }
        $tableName = $installer->getTable('spojit_quote');
        //Check for the existence of the table
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'cart_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => 0],
                    'Cart ID'
                )
                ->addColumn(
                    'carrier_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => 0],
                    'Carrier ID'
                )
                ->addColumn(
                    'method',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Method'
                )
                ->addColumn(
                    'method_title',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Method Title'
                )
                ->addColumn(
                    'price',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Price'
                )
                ->addColumn(
                    'option_id',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Option for freight provider'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Created At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_DATETIME,
                    null,
                    ['nullable' => false],
                    'Updated At'
                )
                ->setComment('Spojit Quote Table');
            $installer->getConnection()->createTable($table);
        }
        $installer->endSetup();
    }
}
