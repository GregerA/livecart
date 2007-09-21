<?php

ClassLoader::import('library.shipping.ShippingRateSet');
ClassLoader::import('library.shipping.ShippingRateResult');
ClassLoader::import('application.model.delivery.ShippingService');

/**
 * Shipping cost calculation result for a particular Shipment. One Shipment can have several
 * ShipmentDeliveryRates - one for each available shipping service. Customer is able to choose between
 * the available rates. ShipmentDeliveryRate can be either a pre-defined rate or a real-time rate.
 *
 * @package application.model.delivery
 * @author Integry Systems <http://integry.com> 
 */
class ShipmentDeliveryRate extends ShippingRateResult implements Serializable
{
    protected $amountWithTax;
    
    /**
     * @var LiveCart
     */
    private $application;
    
    public function setApplication($application)
    {
        $this->application = $application;
    }
    
    public static function getNewInstance(ShippingService $service, $cost)
    {
        $inst = new ShipmentDeliveryRate();
        $inst->setServiceId($service->getID());
        $inst->setApplication($service->getApplication());
        $inst->setCost($cost, $service->getApplication()->getDefaultCurrencyCode());
        return $inst;
    }
    
    public static function getRealTimeRates(ShippingRateCalculator $handler, Shipment $shipment)
    {
        $handler->setWeight($shipment->getChargeableWeight());
        
        $address = $shipment->order->get()->shippingAddress->get();        
        $handler->setDestCountry($address->countryID->get()); 
        
        $handler->setDestZip($address->postalCode->get());
        $config = $shipment->getApplication()->getConfig();
        $handler->setSourceCountry($config->get('STORE_COUNTRY'));
        $handler->setSourceZip($config->get('STORE_ZIP'));
        
        $rates = new ShippingRateSet();

        foreach ($handler->getAllRates() as $k => $rate)        
        {   
            $newRate = new ShipmentDeliveryRate();
            $newRate->setApplication($shipment->getApplication());
            $newRate->setCost($rate->getCostAmount(), $rate->getCostCurrency()); 
            $newRate->setServiceName($rate->getServiceName());
            $newRate->setClassName($rate->getClassName());
            $newRate->setProviderName($rate->getProviderName());
            $newRate->setServiceId($rate->getClassName() . '_' . $k);
            $rates->add($newRate);
        }
        
        return $rates;
    }
    
    public function getAmountByCurrency(Currency $currency)
    {
        $amountCurrency = Currency::getInstanceById($this->getCostCurrency());
        $amount = $currency->convertAmount($amountCurrency, $this->getCostAmount());
        
        return round($amount, 2);
    }
    
    public function setAmountWithTax($amount)
    {
        $this->amountWithTax = $amount;
    }
    
    public function toArray()
    {
        $array = parent::toArray();
        $amountCurrency = Currency::getInstanceById($array['costCurrency']);
        $currencies = $this->application->getCurrencySet();

        // get and format prices
        $prices = $formattedPrices = $taxPrices = array();

        foreach ($currencies as $id => $currency)
        {
            $prices[$id] = $currency->convertAmount($amountCurrency, $array['costAmount']);
            $formattedPrices[$id] = $currency->getFormattedPrice($prices[$id]);
            $taxPrices[$id] = $currency->getFormattedPrice($currency->convertAmount($amountCurrency, $this->amountWithTax));
        }

        $array['price'] = $prices;
        $array['formattedPrice'] = $formattedPrices;
        $array['taxPrice'] = $taxPrices;
                
        // shipping service name
        $id = $this->getServiceID();
        if (is_numeric($id))
        {
            try
            {
                $service = ShippingService::getInstanceById($id, ShippingService::LOAD_DATA);   
                $array['ShippingService'] = $service->toArray();                
            }
            catch (ARNotFoundException $e)
            {
                return array();
            }
        }
        else
        {
            $array['ShippingService'] = array('name_lang' => $this->getServiceName(), 'provider' => $this->getProviderName());
        }
        
        return $array;
    }
    
	public function serialize()
	{
        $vars = get_object_vars($this); 
        unset($vars['application']);
        
        return serialize($vars);
    }
    
    public function unserialize($serialized)
    {
        foreach (unserialize($serialized) as $key => $value)
        {
            $this->$key = $value;
        }
    }
}
?>