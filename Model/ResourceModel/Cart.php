<?php
namespace Spojit\SpojitShipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Cart extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('spojit_cart', 'id');
    }
}
