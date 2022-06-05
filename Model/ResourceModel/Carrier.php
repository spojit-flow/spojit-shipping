<?php
namespace Spojit\SpojitShipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Carrier extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('spojit_carrier', 'id');
    }
}
