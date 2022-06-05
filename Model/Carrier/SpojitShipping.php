<?php

namespace Spojit\SpojitShipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Spojit\SpojitShipping\Client\SpojitClient;
use Spojit\SpojitShipping\Model\CarrierFactory;
use Spojit\SpojitShipping\Model\QuoteFactory;
use Spojit\SpojitShipping\Model\CartFactory;
use Spojit\SpojitShipping\Model\ResourceModel\Carrier\CollectionFactory as CarrierCollectionFactory;
use Spojit\SpojitShipping\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Spojit\SpojitShipping\Model\ResourceModel\Cart\CollectionFactory as CartCollectionFactory;

/**
 * Spojit shipping model
 */
class SpojitShipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'spojitshipping';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var SpojitClient
     */
    private $spojitClient;

    /**
     * @var CarrierFactory
     */
    private $carrierFactory;

    /**
     * @var CartFactory
     */
    private $cartFactory;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CarrierCollectionFactory
     */
    private $carrierCollectionFactory;

    /**
     * @var CartCollectionFactory
     */
    private $cartCollectionFactory;

    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param CarrierFactory $carrierFactory
     * @param CartFactory $cartFactory
     * @param QuoteFactory $quoteFactory
     * @param CarrierCollectionFactory $carrierCollectionFactory
     * @param CartCollectionFactory $cartCollectionFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        CarrierFactory $carrierFactory,
        CartFactory $cartFactory,
        QuoteFactory $quoteFactory,
        CarrierCollectionFactory $carrierCollectionFactory,
        CartCollectionFactory $cartCollectionFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->scopeConfig = $scopeConfig;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->carrierFactory = $carrierFactory;
        $this->cartFactory = $cartFactory;
        $this->quoteFactory = $quoteFactory;
        $this->carrierCollectionFactory = $carrierCollectionFactory;
        $this->cartCollectionFactory = $cartCollectionFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->spojitClient = new SpojitClient($this->getScopeConfig('authorization_token'), $logger);
    }

    /**
     * Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $items = $request->getAllItems();

        if (empty($items)) {
            return false;
        }

        // if we don't have postcode/city/state yet don't try to get quotes
        if (!$request->getDestPostcode() || !$request->getDestCity() || !$request->getDestRegionCode()) {
            return false;
        }

        /** @var \Magento\Quote\Model\Quote\Item $firstItem */
        $firstItem = reset($items);
        if (!$firstItem) {
            return false;
        }

        $quote = $firstItem->getQuote();
        if (!($quote instanceof \Magento\Quote\Model\Quote)) {
            return false;
        }

        $itemsData = [];

        foreach ($items as $item) {
            $itemsData[] = $this->objectToArray($item->getData());
        }

        $data = $this->objectToArray($request->getData());
        $data['all_items'] = $itemsData;

        $cart = $this->cartCollectionFactory->create()->addFieldToFilter('quote_id', $quote->getId())->getFirstItem();

        $cartHash = md5(json_encode($data));
        $refreshQuotes = false;

        if (!$cart->getId()) {
            $cart = $this->cartFactory->create();
            $cart->setQuoteId($quote->getId())->setHash($cartHash)->save();
            $refreshQuotes = true;
        } else {
            if ($cartHash != $cart->getHash()) {
                $cart->setHash($cartHash)->save();
                $refreshQuotes = true;
            }
        }

        if ($refreshQuotes) {
            $this->quoteCollectionFactory->create()->addFieldToFilter('cart_id', $cart->getId())->walk('delete');
            $response = $this->spojitClient->sendRequest($this->getScopeConfig('workflow_token'), $data);

            if ($response && array_key_exists('quotes', $response) && is_array($response['quotes'])) {
                $quotes = $response['quotes'];
            } else {
                return false;
            }

            foreach ($quotes as $quote) {

                // if nothing has been mapped in the quote continue
                if (!array_key_exists('name', $quote)) {
                    continue;
                }

                $methodCode = $this->getMethodCode($quote['name'], $quote['service']);
                $carrier = $this->carrierCollectionFactory->create()->addFieldToFilter('code', $methodCode)->getFirstItem();

                if (!$carrier->getId()) {
                    $carrier = $this->carrierFactory->create();
                    $carrier
                        ->setCode($methodCode)
                        ->setName($quote['name'])
                        ->setService($quote['service'])
                        ->save();
                }

                $quoteModel = $this->quoteFactory->create();
                $quoteModel
                    ->setCartId($cart->getId())
                    ->setCarrierId($carrier->getId())
                    ->setMethod($methodCode)
                    ->setMethodTitle($quote['name'] . ' - ' .  $quote['service'])
                    ->setPrice($quote['price'])
                    ->setOptionId($quote['optionId'])
                    ->save();
            }
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        $quoteCollection = $this->quoteCollectionFactory->create()->addFieldToFilter('cart_id', $cart->getId());
        foreach ($quoteCollection->getItems() as $finalQuote) {

            $allowedMethods = $this->getAllowedMethods();
            if (!array_key_exists($finalQuote->getMethod(), $allowedMethods)) {
                continue;
            }

            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->rateMethodFactory->create();

            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($finalQuote->getMethod());
            $method->setMethodTitle($finalQuote->getMethodTitle());
            $method->setPrice($finalQuote->getPrice());
            $method->setCost($finalQuote->getPrice());

            $result->append($method);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));
        $methods = [];

        $carrierCollection = $this->carrierCollectionFactory->create();
        foreach ($carrierCollection->getItems() as $carrier) {
            if (in_array($carrier->getCode(), $allowedMethods)) {
                $methods[$carrier->getCode()] = sprintf('%s - %s', $carrier->getName(), $carrier->getService());
            }
        }

        return $methods;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function getScopeConfig($field)
    {
        return $this->scopeConfig->getValue(
            'spojit/module_config/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * @param $data
     * @return array
     */
    protected function objectToArray($data)
    {
        return json_decode(json_encode($data), true);
    }


    /**
     * @param $carrierName
     * @param $carrierService
     * @return string
     */
    protected function getMethodCode($carrierName, $carrierService)
    {
        return strtolower(str_replace(' ', '_', sprintf('%s-%s', $carrierName, $carrierService)));
    }
}
