<?php

/**
 * Product specification wrapper class. Loads/modifies product specification data.
 *
 * This class usually should not be used directly as most of the attribute manipulations
 * can be done with Product class itself.
 *
 * @package application.model.eav
 * @author Integry Systems <http://integry.com>
 */
abstract class EavSpecificationManagerCommon
{
	/**
	 * Owner object instance
	 *
	 * @var ActiveRecordModel
	 */
	protected $owner = null;

	protected $attributes = array();

	protected $removedAttributes = array();

	public abstract function getFieldClass();

	public function __construct(ActiveRecordModel $owner, $specificationDataArray = array())
	{
		$this->owner = $owner;
		$this->loadSpecificationData($specificationDataArray);
	}

	/**
	 * Sets specification attribute value by mapping product, specification field, and
	 * assigned value to one record (atomic item)
	 *
	 * @param iEavSpecification $specification Specification item value
	 */
	public function setAttribute(iEavSpecification $newSpecification)
	{
		$specField = $newSpecification->getFieldInstance();
		$itemClass = $specField->getSelectValueClass();

		if(
			$this->owner->isExistingRecord()
			&& isset($this->attributes[$newSpecification->getFieldInstance()->getID()])
			&& ($itemClass == $specField->getSpecificationFieldClass()
			&& $newSpecification->getValue()->isModified())
		)
		{
			// Delete old value
			ActiveRecord::deleteByID($itemClass, $this->attributes[$specField->getID()]->getID());

			// And create new
			$this->attributes[$specField->getID()] = call_user_func_array(array($itemClass, 'getNewInstance'), array($this->owner, $specField, $newSpecification->getValue()->get()));
		}
		else
		{
			$this->attributes[$specField->getID()] = $newSpecification;
		}

		unset($this->removedAttributes[$specField->getID()]);
	}

	/**
	 * Removes persisted product specification property
	 *
	 *	@param SpecField $field SpecField instance
	 */
	public function removeAttribute(EavFieldCommon $field)
	{
		$this->removedAttributes[$field->getID()] = $this->attributes[$field->getID()];
		unset($this->attributes[$field->getID()]);
	}

	public function removeAttributeValue(EavFieldCommon $field, EavValueCommon $value)
	{
		if (!$field->isSelector())
	  	{
			throw new Exception('Cannot remove a value from non selector type specification field');
		}

		if (!isset($this->attributes[$field->getID()]))
		{
		  	return false;
		}

		if ($field->isMultiValue->get())
		{
			$this->attributes[$field->getID()]->removeValue($value);
		}
		else
		{
			// no changes should be made until the save() function is called
			$this->attributes[$field->getID()]->delete();
		}
	}

	public function isAttributeSet(EavFieldCommon $field)
	{
		return isset($this->attributes[$field->getID()]);
	}

	/**
	 *	Get attribute instance for the particular SpecField.
	 *
	 *	If it is a single value selector a SpecFieldValue instance needs to be passed as well
	 *
	 *	@param SpecField $field SpecField instance
	 *	@param SpecFieldValue $defaultValue SpecFieldValue instance (or nothing if SpecField is not selector)
	 *
	 * @return Specification
	 */
	public function getAttribute(EavFieldCommon $field, $defaultValue = null)
	{
		if (!$this->isAttributeSet($field))
		{
		  	$params = array($this->owner, $field, $defaultValue);
			$this->attributes[$field->getID()] = call_user_func_array(array($field->getSpecificationFieldClass(), 'getNewInstance'), $params);
		}

		return $this->attributes[$field->getID()];
	}

	public function save()
	{
		foreach ($this->removedAttributes as $attribute)
		{
		  	$attribute->delete();
		}
		$this->removedAttributes = array();

		foreach ($this->attributes as $attribute)
		{
			$attribute->save();
		}
	}

	public function toArray()
	{
		$arr = array();
		foreach ($this->attributes as $id => $attribute)
		{
			$arr[$id] = $attribute->toArray();
		}

		uasort($arr, array($this, 'sortAttributeArray'));

		return $arr;
	}

	private function sortAttributeArray($a, $b)
	{
		$field = $this->getFieldClass();
		$fieldGroup = $field . 'Group';

		if (!isset($a[$field][$fieldGroup]['position']))
		{
			$a[$field][$fieldGroup]['position'] = -1;
		}

		if (!isset($b[$field][$fieldGroup]['position']))
		{
			$b[$field][$fieldGroup]['position'] = -1;
		}

		if (($a[$field][$fieldGroup]['position'] == $b[$field][$fieldGroup]['position']))
		{
			return ($a[$field]['position'] < $b[$field]['position']) ? -1 : 1;
		}

		return ($a[$field][$fieldGroup]['position'] < $b[$field][$fieldGroup]['position']) ? -1 : 1;
	}

	public static function loadSpecificationForRecordArray(&$productArray)
	{
		$array = array(&$productArray);
		self::loadSpecificationForRecordSetArray($array, true);

		$fieldClass = $this->getFieldClass();
		$groupClass = $this->getFieldClass() . 'Group';
		$groupIDColumn = strtolower(substr($groupClass, 0, 1)) . substr($groupClass, 1) . 'ID';

		$groupIds = array();
		foreach ($productArray['attributes'] as $attr)
		{
			$groupIds[isset($attr[$fieldClass][$groupIDColumn]) ? $attr[$fieldClass][$groupIDColumn] : 'NULL'] = true;
		}

		$f = new ARSelectFilter(new INCond(new ARFieldHandle($groupClass, 'ID'), array_keys($groupIds)));
		$indexedGroups = array();
		$res = ActiveRecordModel::getRecordSetArray($groupClass, $f);
		foreach ($res as $group)
		{
			$indexedGroups[$group['ID']] = $group;
		}

		foreach ($productArray['attributes'] as &$attr)
		{
			if (isset($attr[$fieldClass][$groupIDColumn]))
			{
				$attr[$fieldClass][$groupClass] = $indexedGroups[$attr[$fieldClass][$groupIDColumn]];
			}
		}
	}

	/**
	 * Load product specification data for a whole array of products at once
	 */
	public static function loadSpecificationForRecordSetArray($class, &$productArray, $fullSpecification = false)
	{
		$ids = array();
		foreach ($productArray as $key => $product)
	  	{
			$ids[$product['ID']] = $key;
		}

		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$stringClass = call_user_func(array($fieldClass, 'getStringValueClass'));
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$objectColumn = call_user_func(array($fieldClass, 'getObjectIDColumnName'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueColumn = call_user_func(array($valueItemClass, 'getValueIDColumnName'));

		$specificationArray = self::fetchSpecificationData($class, array_flip($ids), $fullSpecification);

		$specFieldSchema = ActiveRecordModel::getSchemaInstance($fieldClass);
		$specStringSchema = ActiveRecordModel::getSchemaInstance($stringClass);
		$specFieldColumns = array_keys($specFieldSchema->getFieldList());

		foreach ($specificationArray as &$spec)
		{
			if ($spec['isMultiValue'])
			{
				$value['value'] = $spec['value'];
				$value = MultiLingualObject::transformArray($value, $specStringSchema);

				if (isset($productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]]))
				{
					$sp =& $productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]];
					$sp['valueIDs'][] = $spec[$valueColumn];
					$sp['values'][] = $value;
					continue;
				}
			}

			foreach ($specFieldColumns as $key)
			{
				$spec[$fieldClass][$key] = $spec[$key];
				unset($spec[$key]);
			}

			// transform for presentation
			$spec[$fieldClass] = MultiLingualObject::transformArray($spec[$fieldClass], $specFieldSchema);

			if ($spec[$fieldClass]['isMultiValue'])
			{
				$spec['valueIDs'] = array($spec[$valueColumn]);
				$spec['values'] = array($value);
			}
			else
			{
				$spec = MultiLingualObject::transformArray($spec, $specStringSchema);
			}

			if ((!empty($spec['value']) || !empty($spec['values']) || !empty($spec['value_lang'])))
			{
				// append to product array
				$productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]] = $spec;
				Product::sortAttributesByHandle($productArray[$ids[$spec[$objectColumn]]]);
			}
		}
	}

	protected static function fetchSpecificationData($class, $objectIDs, $fullSpecification = false)
	{
		if (!$objectIDs)
		{
			return array();
		}

		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$groupClass = $fieldClass . 'Group';
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$objectColumn = call_user_func(array($fieldClass, 'getObjectIDColumnName'));
		$stringClass = call_user_func(array($fieldClass, 'getStringValueClass'));
		$numericClass = call_user_func(array($fieldClass, 'getNumericValueClass'));
		$dateClass = call_user_func(array($fieldClass, 'getDateValueClass'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueClass = call_user_func(array($valueItemClass, 'getValueClass'));
		$valueColumn = call_user_func(array($valueItemClass, 'getValueIDColumnName'));
		$groupColumn = strtolower(substr($groupClass, 0, 1)) . substr($groupClass, 1) . 'ID';

		$cond = '
		LEFT JOIN
			' . $fieldClass . ' ON ' . $fieldColumn . ' = ' . $fieldClass . '.ID
		LEFT JOIN
			' . $groupClass . ' ON ' . $fieldClass . '.' . $groupColumn . ' = ' . $groupClass . '.ID
		WHERE
			' . $objectColumn . ' IN (' . implode(', ', $objectIDs) . ')' . ($fullSpecification ? '' : ' AND ' . $fieldClass . '.isDisplayedInList = 1');

		$query = '
		SELECT ' . $dateClass . '.*, NULL AS ' . $valueColumn . ', NULL AS specFieldValuePosition, ' . $groupClass . '.position AS SpecFieldGroupPosition, ' . $fieldClass . '.* as valueID FROM ' . $dateClass . ' ' . $cond . '
		UNION
		SELECT ' . $stringClass . '.*, NULL, NULL AS specFieldValuePosition, ' . $groupClass . '.position, ' . $fieldClass . '.* as valueID FROM ' . $stringClass . ' ' . $cond . '
		UNION
		SELECT ' . $numericClass . '.*, NULL, NULL AS specFieldValuePosition, ' . $groupClass . '.position, ' . $fieldClass . '.* as valueID FROM ' . $numericClass . ' ' . $cond . '
		UNION
		SELECT ' . $valueItemClass . '.' . $objectColumn . ', ' . $valueItemClass . '.' . $fieldColumn . ', ' . $valueClass . '.value, ' . $valueClass . '.ID, ' . $valueClass . '.position, ' . $groupClass . '.position, ' . $fieldClass . '.*
				 FROM ' . $valueItemClass . '
				 	LEFT JOIN ' . $valueClass . ' ON ' . $valueItemClass . '.' . $valueColumn . ' = ' . $valueClass . '.ID
				 ' . str_replace('ON ' . $fieldColumn, 'ON ' . $valueItemClass . '.' . $fieldColumn, $cond) .
				 ' ORDER BY ' . $objectColumn . ', SpecFieldGroupPosition, position, specFieldValuePosition';

		$specificationArray = ActiveRecordModel::getDataBySQL($query);

		$multiLingualFields = array('name', 'description', 'valuePrefix', 'valueSuffix');

		foreach ($specificationArray as &$spec)
		{
			// unserialize language field values
			foreach ($multiLingualFields as $value)
			{
				$spec[$value] = unserialize($spec[$value]);
			}

			if ((EavFieldCommon::DATATYPE_TEXT == $spec['dataType'] && EavFieldCommon::TYPE_TEXT_DATE != $spec['type'])
				|| (EavFieldCommon::TYPE_NUMBERS_SELECTOR == $spec['type']))
			{
				$spec['value'] = unserialize($spec['value']);
			}
		}

		return $specificationArray;
	}

	protected function loadSpecificationData($specificationDataArray)
	{
		// get value class and field names
		$fieldClass = $this->getFieldClass();
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueClass = call_user_func(array($valueItemClass, 'getValueClass'));
		$multiValueItemClass = call_user_func(array($fieldClass, 'getMultiSelectValueClass'));

		// preload all specFields from database
		$specFieldIds = array();

		$selectors = array();
		$simpleValues = array();
		foreach ($specificationDataArray as $value)
		{
		  	$specFieldIds[$value[$fieldColumn]] = $value[$fieldColumn];
		  	if ($value['valueID'])
		  	{
		  		$selectors[$value[$fieldColumn]][$value['valueID']] = $value;
			}
			else
			{
				$simpleValues[$value[$fieldColumn]] = $value;
			}
		}

		$specFields = ActiveRecordModel::getInstanceArray($fieldClass, $specFieldIds);

		// simple values
		foreach ($simpleValues as $value)
		{
		  	$specField = $specFields[$value[$fieldColumn]];

		  	$class = $specField->getValueTableName();

			$specification = call_user_func_array(array($class, 'restoreInstance'), array($this->owner, $specField, $value['value']));
		  	$this->attributes[$specField->getID()] = $specification;
		}

		// selectors
		foreach ($selectors as $specFieldId => $value)
		{
			$specField = $specFields[$specFieldId];
		  	if ($specField->isMultiValue->get())
		  	{
				$values = array();
				foreach ($value as $val)
				{
					$values[$val['valueID']] = $val['value'];
				}
				$specification = call_user_func_array(array($multiValueItemClass, 'restoreInstance'), array($this->owner, $specField, $values));
			}
			else
			{
			  	$value = array_pop($value);
				$specFieldValue = call_user_func_array(array($valueClass, 'restoreInstance'), array($specField, $value['valueID'], $value['value']));
				$specification = call_user_func_array(array($valueItemClass, 'restoreInstance'), array($this->owner, $specField, $specFieldValue));
			}

		  	$this->attributes[$specField->getID()] = $specification;
		}
	}

	public function __destruct()
	{
		foreach ($this->attributes as $k => $attr)
		{
			$this->attributes[$k]->__destruct();
			unset($this->attributes[$k]);
		}

		foreach ($this->removedAttributes as $k => $attr)
		{
			$this->removedAttributes[$k]->__destruct();
			unset($this->removedAttributes[$k]);
		}

		unset($this->owner);
	}
}

?>