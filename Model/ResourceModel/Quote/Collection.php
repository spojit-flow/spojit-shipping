<?php

namespace Spojit\SpojitShipping\Model\ResourceModel\Quote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Spojit\SpojitShipping\Model\Quote',
            'Spojit\SpojitShipping\Model\ResourceModel\Quote'
        );
    }
}
