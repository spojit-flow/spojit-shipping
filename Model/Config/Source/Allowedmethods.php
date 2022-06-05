<?php

namespace Spojit\SpojitShipping\Model\Config\Source;

use Spojit\SpojitShipping\Model\ResourceModel\Carrier\CollectionFactory;

class Allowedmethods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    private $carrierCollectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->carrierCollectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $carrierCollection = $this->carrierCollectionFactory->create();
        foreach ($carrierCollection->getItems() as $carrier) {
            $options[] = ['value' => $carrier->getCode(), 'label' => sprintf('%s - %s', $carrier->getName(), $carrier->getService())];
        }
        return $options;
    }
}
