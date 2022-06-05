<?php

namespace Spojit\SpojitShipping\Model\ResourceModel\Cart;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Spojit\SpojitShipping\Model\Cart',
            'Spojit\SpojitShipping\Model\ResourceModel\Cart'
        );
    }
}
