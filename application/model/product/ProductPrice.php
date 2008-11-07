<?php

ClassLoader::import("application.model.ActiveRecordModel");
ClassLoader::import("application.model.Currency");

/**
 * Product price class. Prices can be entered in different currencies.
 * Each instance of ProductPrice determines product price in a particular currency.
 *
 * @package application.model.product
 * @author Integry Systems <http://integry.com>
 */
class ProductPrice extends ActiveRecordModel
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName("ProductPrice");

		$schema->registerField(new ARPrimaryForeignKeyField("productID", "Product", "ID", null, ARInteger::instance()));
		$schema->registerField(new ARPrimaryForeignKeyField("currencyID", "Currency", "ID", null, ARChar::instance(3)));
		$schema->registerField(new ARField("price", ARFloat::instance(16)));
		$schema->registerField(new ARField("listPrice", ARFloat::instance(16)));
		$schema->registerField(new ARField("serializedRules", ARText::instance(16)));
	}

	/*####################  Static method implementations ####################*/

	public static function getNewInstance(Product $product, Currency $currency)
	{
		$instance = parent::getNewInstance(__CLASS__);
		$instance->product->set($product);
		$instance->currency->set($currency);
		return $instance;
	}

	public static function getInstance(Product $product, Currency $currency)
	{
		$filter = new ARSelectFilter();
		$cond = new EqualsCond(new ARFieldHandle('ProductPrice', 'productID'), $product->getID());
		$cond->addAND(new EqualsCond(new ARFieldHandle('ProductPrice', 'currencyID'), $currency->getID()));
		$filter->setCondition($cond);

		$set = parent::getRecordSet('ProductPrice', $filter);

		if ($set->size() > 0)
		{
		  	$instance = $set->get(0);
		}
		else
		{
		  	$instance = self::getNewInstance($product, $currency);
		}

		return $instance;
	}

	/**
	 * Loads a set of active record product price by using a filter
	 *
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 *
	 * @return ARSet
	 */
	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		return parent::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}

	/*####################  Value retrieval and manipulation ####################*/

	public function getPrice()
	{
		$price = $this->price->get();

		if ($parent = $this->product->get()->parent->get())
		{
			$parentPrice = $parent->getPricingHandler()->getPrice($this->currency->get())->getPrice();

			if ($this->product->get()->getChildSetting('price') == Product::CHILD_ADD)
			{
				return $parentPrice + $price;
			}
			else if ($this->product->get()->getChildSetting('price') == Product::CHILD_SUBSTRACT)
			{
				return $parentPrice - $price;
			}
			else if ((float)$price)
			{
				return $price;
			}
			else
			{
				return $parentPrice;
			}
		}
		else
		{
			return $price;
		}
	}

	public function getItemPrice(OrderedItem $item)
	{
		if ($this->getPrice())
		{
			$rules = unserialize($this->serializedRules->get());
			$price = $this->getPrice();

			// quantity/group based prices
			if ($rules)
			{
				$user = $item->customerOrder->get()->user->get();
				$groupID = ($user && $user->userGroup->get()) ? $user->userGroup->get()->getID() : 0;

				foreach (array($groupID, 0) as $group)
				{
					$p = $this->getGroupPrice($item, $group, $rules);
					if (!is_null($p))
					{
						return $p;
					}
				}
			}

			return $price;
		}
		else if ($this->currency->get()->getID() != self::getApplication()->getDefaultCurrencyCode())
		{
			$defaultCurrency = self::getApplication()->getDefaultCurrency();
			return $this->convertFromDefaultCurrency($this->product->get()->getItemPrice($item, $defaultCurrency->getID()));
		}

		return 0;
	}

	private function getGroupPrice(OrderedItem $item, $groupID, $rules)
	{
		$found = array();
		foreach ($rules as $quant => $prices)
		{
			if (isset($prices[$groupID]))
			{
				$found[$quant] = $prices[$groupID];
			}
		}

		$quantities = array_keys($found);
		sort($quantities);
		$cnt = count($quantities);
		$itemCnt = $item->count->get();

		for ($k = 0; $k < $cnt; $k++)
		{
			if ($quantities[$k] <= $itemCnt && (($k == $cnt - 1) || ($quantities[$k + 1] > $itemCnt)))
			{
				return $found[$quantities[$k]];
			}
		}

		return null;
	}

	public function reCalculatePrice()
	{
		$defaultCurrency = self::getApplication()->getDefaultCurrency();
		return $this->convertFromDefaultCurrency($this->product->get()->getPrice($defaultCurrency->getID(), Product::DO_NOT_RECALCULATE_PRICE));
	}

	private function convertFromDefaultCurrency($price)
	{
		if ($this->currency->get()->rate->get())
		{
			return $price / $this->currency->get()->rate->get();
		}
		else
		{
			return 0;
		}
	}

	public function increasePriceByPercent($percentIncrease)
	{
		$multiply = (100 + $percentIncrease) / 100;
		$this->price->set($this->price->get() * $multiply);
	}

	public function setPriceRule($quantity, UserGroup $group = null, $price)
	{
		$rules = unserialize($this->serializedRules->get());
		$rules[$quantity][is_null($group) ? 0 : $group->getID()] = $price;
		$this->setRules($rules);
	}

	public function removePriceRule($quantity, UserGroup $group = null)
	{
		$rules = unserialize($this->serializedRules->get());
		unset($rules[$quantity][is_null($group) ? 0 : $group->getID()]);
		if (empty($rules[$quantity]))
		{
			unset($rules[$quantity]);
		}
		$this->setRules($rules);
	}

	public function getUserPrices(User $user = null)
	{
		$id = $this->getGroupId($user);
		$rules = unserialize($this->serializedRules->get());
		$found = array();

		if (is_array($rules))
		{
			foreach ($rules as $quant => $prices)
			{
				if (isset($prices[$id]))
				{
					$found[$quant] = $prices[$id];
				}
			}
		}

		if ($id > 0 && !$found)
		{
			return $this->getUserPrices(null);
		}

		return $found;
	}

	private function getGroupId(User $user = null)
	{
		if (!$user)
		{
			return 0;
		}

		return is_null($user->userGroup->get()) ? 0 : $user->userGroup->get()->getID();
	}

	private function setRules($rules)
	{
		ksort($rules);
		$this->serializedRules->set(serialize($rules));
	}

	public static function calculatePrice(Product $product, Currency $currency, $basePrice = null)
	{
		if (is_null($basePrice))
		{
			$defaultCurrency = self::getApplication()->getDefaultCurrencyCode();
			$basePrice = $product->getPrice($defaultCurrency, Product::DO_NOT_RECALCULATE_PRICE);
		}

		return self::convertPrice($currency, $basePrice);
	}

	public static function convertPrice(Currency $currency, $basePrice)
	{
		$rate = (float)$currency->rate->get();
		if ($rate)
		{
			$price = $basePrice / $rate;
		}
		else
		{
			$price = 0;
		}

		return $price;
	}

	/*####################  Instance retrieval ####################*/

	/**
	 * Load product pricing data for a whole array of products at once
	 */
	public static function loadPricesForRecordSetArray(&$productArray)
	{
		$ids = array();
		foreach ($productArray as $key => $product)
	  	{
			$ids[$product['ID']] = $key;
		}

		$prices = self::fetchPriceData(array_keys($ids));

		// sort by product
		$listPrice = $productPrices = array();
		foreach ($prices as $price)
		{
			$productPrices[$price['productID']][$price['currencyID']] = $price['price'];
			$listPrices[$price['productID']][$price['currencyID']] = $price['listPrice'];
		}

		self::getPricesFromArray($productArray, $productPrices, $ids, false);
		if (isset($listPrices))
		{
			self::getPricesFromArray($productArray, $listPrices, $ids, true);
		}
	}

	private static function getPricesFromArray(&$productArray, $priceArray, $ids, $listPrice = false)
	{
		$baseCurrency = self::getApplication()->getDefaultCurrencyCode();
		$currencies = self::getApplication()->getCurrencySet();

		$priceField = $listPrice ? 'listPrice' : 'price';
		$formattedPriceField = $listPrice ? 'formattedListPrice' : 'formattedPrice';

		foreach ($priceArray as $product => $prices)
		{
			// look for a parent product
			if (!empty($productArray[$ids[$product]]['parentID']))
			{
				$parent = Product::getInstanceByID($productArray[$ids[$product]]['parentID']);
				$settings = $productArray[$ids[$product]]['childSettings'];
				if (isset($settings['price']))
				{
					$priceSetting = $settings['price'];
				}
			}
			else
			{
				$parent = null;
			}

			foreach ($currencies as $id => $currency)
			{
				if (!isset($prices[$id]))
				{
					$prices[$id] = self::convertPrice($currency, isset($prices[$baseCurrency]) ? $prices[$baseCurrency] : 0);
				}
			}

			foreach ($prices as $id => $price)
			{
				if ((0 == $price) && $listPrice)
				{
					continue;
				}

				if ($parent && (($priceSetting != Product::CHILD_OVERRIDE) || !$price))
				{
					$parentPrice = $parent->getPrice($id);
					$price = $parentPrice + ($price * (($priceSetting == Product::CHILD_ADD) ? 1 : -1));
				}

				$productArray[$ids[$product]][$priceField . '_' . $id] = $price;
				if (isset($currencies[$id]))
				{
					$productArray[$ids[$product]][$formattedPriceField][$id] = $currencies[$id]->getFormattedPrice($price);
				}
			}
		}
	}

	private static function fetchPriceData($productIDs)
	{
		if (!$productIDs)
		{
			return array();
		}

		$baseCurrency = self::getApplication()->getDefaultCurrencyCode();

		$filter = new ARSelectFilter(new INCond(new ARFieldHandle('ProductPrice', 'productID'), $productIDs));
		$filter->setOrder(new ARExpressionHandle('currencyID = "' . $baseCurrency . '"'), 'DESC');
		return ActiveRecordModel::getRecordSetArray('ProductPrice', $filter);
	}

	/**
	 * Load product pricing data for a whole array of products at once
	 */
	public static function loadPricesForRecordSet(ARSet $products)
	{
		$set = ARSet::buildFromArray($products->getData());
		foreach ($products as $key => $product)
	  	{
			if ($product->parent->get())
			{
				$set->add($product->parent->get());
			}
		}

		$ids = array();
		foreach ($set as $key => $product)
	  	{
			$ids[$product->getID()] = $key;
		}

		$priceArray = self::fetchPriceData(array_flip($ids));

		$pricing = array();
		foreach ($priceArray as $price)
		{
			$pricing[$price['productID']][$price['currencyID']] = $price;
		}

		foreach ($pricing as $productID => $productPricing)
		{
			$product = $set->get($ids[$productID]);
			$product->loadPricing($productPricing);
		}
	}

	/**
	 * Get record set of product prices
	 *
	 * @param Product $product
	 *
	 * @return ARSet
	 */
	public static function getProductPricesSet(Product $product)
	{
		// preload currency data (otherwise prices would have to be loaded with referenced records)
		self::getApplication()->getCurrencySet();

		return self::getRecordSet(self::getProductPricesFilter($product));
	}

	/**
	 * Get product prices filter
	 *
	 * @param Product $product
	 *
	 * @return ARSelectFilter
	 */
	private static function getProductPricesFilter(Product $product)
	{
		ClassLoader::import("application.model.Currency");

		return new ARSelectFilter(new EqualsCond(new ARFieldHandle(__CLASS__, 'productID'), $product->getID()));
	}

	public static function transformArray($array, ARSchema $schema)
	{
		$array = parent::transformArray($array, $schema);
		$array['serializedRules'] = unserialize($array['serializedRules']);
		return $array;
	}
}

?>
