<?php

namespace Spojit\SpojitShipping\Cron;

use Spojit\SpojitShipping\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;

class CleanQuotes
{
    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @param QuoteCollectionFactory $quoteCollectionFactory
     */
    public function __construct(QuoteCollectionFactory $quoteCollectionFactory)
    {
            $this->quoteCollectionFactory = $quoteCollectionFactory;
    }

    /**
     * @return $this
     */
    public function execute()
    {
        $datetime = new \DateTime();
        $datetime->modify('-7 days');
        $this->quoteCollectionFactory->create()->addFieldToFilter('created_at', ['lteq' => $datetime->format('Y-m-d H:i:s')])->walk('delete');

        return $this;

    }
}
