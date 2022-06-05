<?php

namespace Spojit\SpojitShipping\Model;

use Magento\Framework\Model\AbstractModel;

class Carrier extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('Spojit\SpojitShipping\Model\ResourceModel\Carrier');
    }

    public function beforeSave()
    {
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt(new \DateTime());
        }

        $this->setData('updated_at', new \DateTime());

        return parent::beforeSave();
    }
}
