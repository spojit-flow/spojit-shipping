<?php

namespace Spojit\SpojitShipping\Model\ResourceModel\Carrier;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Spojit\SpojitShipping\Model\Carrier',
            'Spojit\SpojitShipping\Model\ResourceModel\Carrier'
        );
    }
}
