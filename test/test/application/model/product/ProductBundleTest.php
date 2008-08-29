<?php
if(!defined('TEST_SUITE')) require_once dirname(__FILE__) . '/../../Initialize.php';

ClassLoader::import("application.model.product.Product");
ClassLoader::import("application.model.order.CustomerOrder");

/**
 *  @author Integry Systems
 *  @package test.model.category
 */
class ProductBundleTest extends UnitTest
{
	private $product;
	private $root;
	private $user;

	public function getUsedSchemas()
	{
		return array(
			'Product',
			'User',
			'CustomerOrder',
			'OrderedItem',
		);
	}

	public function setUp()
	{
		parent::setUp();

		$this->root = Category::getRootNode();
		$this->container = Product::getNewInstance($this->root);
		$this->container->type->set(Product::TYPE_BUNDLE);
		$this->container->save();
	}

	public function testCreateAndRetrieve()
	{
		$products = array();
		for ($k = 0; $k <= 2; $k++)
		{
			$products[$k] = Product::getNewInstance($this->root);
			$products[$k]->save();

			$bundled = ProductBundle::getNewInstance($this->container, $products[$k]);
			$bundled->save();
		}

		$list = ProductBundle::getBundledProductSet($this->container);
		$this->assertEqual($list->size(), count($products));

		foreach ($list as $index => $item)
		{
			$this->assertSame($item->relatedProduct->get(), $products[$index]);
		}
	}

	/**
	 *	@expectedException ProductRelationshipException
	 */
	public function testBundlingProductToItself()
	{
		ProductBundle::getNewInstance($this->container, $this->container);
	}

	public function testShippingWeight()
	{
		$product1 = Product::getNewInstance($this->root);
		$product1->shippingWeight->set(100);
		$product1->save();

		$product2 = Product::getNewInstance($this->root);
		$product2->shippingWeight->set(200);
		$product2->save();

		ProductBundle::getNewInstance($this->container, $product1)->save();
		ProductBundle::getNewInstance($this->container, $product2)->save();

		$this->assertEqual($this->container->getShippingWeight(), 300);
	}

	public function testAvailability()
	{
		$product1 = Product::getNewInstance($this->root);
		$product1->save();

		$product2 = Product::getNewInstance($this->root);
		$product2->save();

		ProductBundle::getNewInstance($this->container, $product1)->save();
		ProductBundle::getNewInstance($this->container, $product2)->save();

		// bundle container not enabled
		$this->assertTrue($product1->isAvailable() && $product2->isAvailable());
		$this->assertFalse($this->container->isAvailable());

		$this->container->isEnabled->set(true);
		$this->assertTrue($this->container->isAvailable());

		// turn on inventory tracking
		$this->config->set('INVENTORY_TRACKING', 'ENABLE_AND_HIDE');
		$product1->stockCount->set(2);
		$product1->save();

		$product2->stockCount->set(2);
		$product2->save();

		$this->assertTrue($this->container->isAvailable());

		// remove inventory for one product
		$product2->stockCount->set(0);
		$product2->save();

		$this->assertFalse($this->container->isAvailable());
	}

}

?>