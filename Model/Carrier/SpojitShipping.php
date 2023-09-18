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
        try {
            return $this->getResult($request);
        } catch (\Exception $e) {
            $this->_logger->error(sprintf('SPOJIT ERROR: %s', $e->getMessage()));
            return false;
        }

    }

    /**
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    protected function getResult(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $items = $request->getAllItems();

        if (empty($items)) {
            return false;
        }

        // if we don't have postcode don't try to get quotes
        if (!$request->getDestPostcode()) {
            return false;
        }

        $hashCheck = ['postcode' => $request->getDestPostcode()];

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
            $itemsDataArray = $this->objectToArray($item->getData());
            if ($itemsDataArray['product_type'] == 'configurable') {
                continue;
            }
            $unitQty = array_key_exists('unit_qty', $itemsDataArray) ?  $itemsDataArray['unit_qty']: null;
            // I don't know how the unit_qty renders but it doesn't seems to be an int
            if ($unitQty == '0') {
                $unitQty = null;
            }
            $qty = $unitQty ? ($unitQty * $itemsDataArray['qty']) : $itemsDataArray['qty'];
            $hashCheck['items'][] = ['unit_qty' => $unitQty, 'product' => $itemsDataArray['product_id'], 'qty' => $itemsDataArray['qty']];
            $itemData = [
                'ref' => $itemsDataArray['sku'],
                'amt' => $qty,
                'desc' => 'CARTON',
                'wgt' => $itemsDataArray['weight'] * $qty,
                'youritemdesc' => substr($itemsDataArray['name'], 0, 50),
                'multiplylwh' => 'Y',
                'len' => 1,
                'wdt' => 1,
                'hgt' => 1,
                'cube' => ($itemsDataArray['weight'] * $qty * 1 * 1 * 1)/1000000, // the *1 are placeholders for lwh if they are added
            ];

            if (array_key_exists('is_dangerous', $itemsDataArray) && $itemsDataArray['is_dangerous'] == '1') {
                $itemData['unnumber'] = '1111';
                $itemData['class'] = '2.1';
                $itemData['size'] = $qty;
                $itemData['um'] = 'KG';
            }

            $itemsData[] = $itemData;
        }

        $data = $this->objectToArray($request->getData());
        $data['all_items'] = $itemsData;

        $cart = $this->cartCollectionFactory->create()->addFieldToFilter('quote_id', $quote->getId())->getFirstItem();

        $cartHash = md5(json_encode($hashCheck));
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
            $foundData = null;
            $postcodes = file_get_contents(__DIR__  . '/postcodes.json');
            foreach (json_decode($postcodes, true) as $postcodeData) {
                if ($postcodeData['postcode'] == $data['dest_postcode']) {
                    $foundData = $postcodeData;
                    break;
                }
            }

            $data['final_address'] = [
                'dest_street' => $data['dest_street'],
                'dest_region_code' => $foundData ? $foundData['state'] : $data['dest_region_code'],
                'dest_city' => $foundData ? $foundData['locality'] : $data['dest_city'],
                'dest_postcode' => $foundData ? $foundData['postcode'] : $data['dest_postcode'],
            ];

            $this->quoteCollectionFactory->create()->addFieldToFilter('cart_id', $cart->getId())->walk('delete');
            $response = $this->spojitClient->sendRequest($this->getScopeConfig('workflow_token'), $data);

            if ($response &&
                array_key_exists('GetDeliveryOptionsResult', $response) &&
                array_key_exists('otheroptions', $response['GetDeliveryOptionsResult']) &&
                array_key_exists('DeliveryOption', $response['GetDeliveryOptionsResult']['otheroptions']) &&
                is_array($response['GetDeliveryOptionsResult']['otheroptions']['DeliveryOption'])) {
                $quotes = $response['GetDeliveryOptionsResult']['otheroptions']['DeliveryOption'];
            } else {
                return false;
            }

            foreach ($quotes as $quote) {

                // if nothing has been mapped in the quote continue
                if (!array_key_exists('carriername', $quote)) {
                    continue;
                }

                $service = $quote['service'];

                if (array_key_exists('serviceoption', $quote)) {
                    if (array_key_exists('option', $quote['serviceoption']) && $quote['serviceoption']['option']) {
                        $service = $service . ' ' . $quote['serviceoption']['option'];
                    }
                };

                $methodCode = $this->getMethodCode($quote['carriername'], $service);
                $carrier = $this->carrierCollectionFactory->create()->addFieldToFilter('code', $methodCode)->getFirstItem();

                if (!$carrier->getId()) {
                    $carrier = $this->carrierFactory->create();
                    $carrier
                        ->setCode($methodCode)
                        ->setName($quote['carriername'])
                        ->setService($service)
                        ->save();
                }

                $quoteModel = $this->quoteFactory->create();
                $quoteModel
                    ->setCartId($cart->getId())
                    ->setCarrierId($carrier->getId())
                    ->setMethod($methodCode)
                    ->setMethodTitle($quote['carriername'] . ' - ' .  $service)
                    ->setPrice(str_replace(',', '', $quote['primarypricing']))
                    ->setOptionId($quote['optionid'])
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
