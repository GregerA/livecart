<?php
if(!defined('TEST_SUITE')) require_once dirname(__FILE__) . '/../../Initialize.php';

ClassLoader::import("application.model.delivery.ShippingService");

class TestShippingService extends UnitTest
{
    /**
     * Delivery zone
     *
     * @var DeliveryZone
     */
    private $deliveryZone = null;

    public function __construct()
    {
        parent::__construct('shiping service tests');
    }
    
    public function getUsedSchemas()
    {
        return array(
			'ShippingService',
            'DeliveryZone'
        );
    }
    
    public function setUp()
    {
        parent::setUp();
        
        $this->deliveryZone = DeliveryZone::getNewInstance();
        $this->deliveryZone->setValueByLang('name', 'en', 'test zone');
        $this->deliveryZone->save();
    }
    
    public function testCreateNewService()
    {
        $service = ShippingService::getNewInstance($this->deliveryZone, 'Test service', ShippingService::SUBTOTAL_BASED);
        $service->position->set(1);
        $service->save();
        
        $service->markAsNotLoaded();
        $service->load();
        
        $this->assertEqual($service->getValueByLang('name', 'en'), 'Test service');
        $this->assertEqual($service->position->get(), 1);
        $this->assertTrue($service->deliveryZone->get() === $this->deliveryZone);
        $this->assertEqual($service->rangeType->get(), ShippingService::SUBTOTAL_BASED);
    }
}
?>