<?php
ClassLoader::import("application.model.system.MultilingualObject");

/**
 * Filter group model
 *
 * @package application.model.category
 */
class FilterGroup extends MultilingualObject
{
    /**
     * Define FilterGroup database schema
     */
	public static function defineSchema()
	{
		$schema = self::getSchemaInstance(__CLASS__);
		$schema->setName(__CLASS__);

		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField("specFieldID", "SpecField", "ID", "SpecField", ARInteger::instance()));
		$schema->registerField(new ARField("name", ARArray::instance()));
		$schema->registerField(new ARField("position", ARInteger::instance()));
		$schema->registerField(new ARField("isEnabled", ARInteger::instance(1)));
	}

	/**
	 * Get new instance of FilterGroup record
	 *
	 * @return ActiveRecord
	 */
	public static function getNewInstance(SpecField $specField)
	{
		$inst = parent::getNewInstance(__CLASS__);
		$inst->specField->set($specField);
		return $inst;
	}

	/**
	 * Get FilterGroup active record instance
	 *
	 * @param integer $recordID
	 * @param boolean $loadRecordData
	 * @param boolean $loadReferencedRecords
	 * @return Filter
	 */
	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false)
	{
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}
	
	/**
	 * This method is checking if SpecField record with passed id exist in the database
	 *
	 * @param int $id Record id
	 * @return boolean
	 */
	public static function exists($id)
	{
	    return ActiveRecord::objectExists(__CLASS__, (int)$id);
	}
	
	/**
	 * Add new filter to filter group
	 *
	 * @param Filter $filter
	 */
	public function addFilter(Filter $filter)
	{
		$filter->filterGroup->set($this);
		$filter->save();
	}

	/**
	 * Delete filter group from database by id
	 *
	 * @param integer $id
	 * @return boolean
	 */
	public static function deletebyID($id)
	{
	    return parent::deleteByID(__CLASS__, $id);
	}
	
	/**
	 * Get record set of filter groups using select filter 
	 *
	 * @param ARSelectFilter $filter
	 * @return ARSet
	 */
	public static function getRecordSetArray(ARSelectFilter $filter)
	{
	    return parent::getRecordSetArray(__CLASS__, $filter);
	}

	/**
	 * Get record set as array of filter groups using select filter 
	 *
	 * @param ARSelectFilter $filter
	 * @return array
	 */
	public static function getRecordSet(ARSelectFilter $filter)
	{
	    return parent::getRecordSet(__CLASS__, $filter);
	}

	/**
	 * Loads a set of spec field records in current category
	 *
	 * @return ARSet
	 */
	public function getFiltersList()
	{
		$filter = new ARSelectFilter();
		$filter->setOrder(new ARFieldHandle("Filter", "position"));
		$filter->setCondition(new EqualsCond(new ARFieldHandle("Filter", "filterGroupID"), $this->getID()));

		return Filter::getRecordSet($filter);
	}
	
	/**
	 * Save group filters in database
	 *
	 * @param array $filters
	 * @param int $specFieldType 
	 * @param array $languages
	 */
    public function saveFilters($filters, $specFieldType, $languageCodes) 
    {
        $position = 1;
        $filtersCount = count($filters);
        $i = 0;
            
        $newIDs = array();
        foreach ($filters as $key => $value)
        {
            // Ignore last new empty filter
            $i++;
            if($filtersCount == $i && $value['name'][$languageCodes[0]] == '' && preg_match("/new/", $key)) continue;
            
            if(preg_match('/^new/', $key))
            {
                $filter = Filter::getNewInstance($this);
            }
            else
            {
                $filter = Filter::getInstanceByID((int)$key);
            }

            $filter->setLanguageField('name', $value['name'], $languageCodes);
            
            if($specFieldType == SpecField::TYPE_TEXT_DATE)
            {
                $filter->rangeDateStart->set($value['rangeDateStart']);
                $filter->rangeDateEnd->set($value['rangeDateEnd']);
                $filter->rangeStart->setNull();
                $filter->rangeEnd->setNull();
            }
            else
            {
                $filter->rangeDateStart->setNull();
                $filter->rangeDateEnd->setNull();
                $filter->rangeStart->set($value['rangeStart']);
                $filter->rangeEnd->set($value['rangeEnd']);
            }
            
            $filter->filterGroup->set($this);
            $filter->position->set($position++);
            $filter->save();
            
            if(preg_match('/^new/', $key))
            {
                $newIDs[$filter->getID()] = $key;
            }
                
        }
        
        return $newIDs;
    }

	protected function insert()
	{
		$this->position->set(100000);  			
		return parent::insert();
	}

	/**
	 * Count filter groups in this category
	 *
	 * @param Category $category Category active record
	 * @return integer
	 */
    public static function countItems(Category $category)
    {
        return $category->getFilterGroupSet(false)->getTotalRecordCount();
    }	

    /**
     * Validates filter group form
     *
     * @param array $values List of values to validate.
     * @return array List of all errors
     */
    public static function validate($values = array(), $languageCodes)
    {
        $errors = array();
        
        if(!isset($values['name']) || $values['name'][$languageCodes[0]] == '')
        {
            $errors['name['.$languageCodes[0].']'] = '_error_name_empty';
        }

        $specField = SpecField::getInstanceByID((int)$values['specFieldID']);
        if(!$specField->isLoaded()) $specField->load();
        
        if(isset($values['filters']) && !$specField->isSelector())
        {                 
            $filtersCount = count($values['filters']);
            $i = 0;
            foreach ($values['filters'] as $key => $v)
            {                
                $i++;
                // If emty last new filter, ignore it
                if($filtersCount == $i && $v['name'][$languageCodes[0]] == '' && preg_match("/new/", $key)) continue;

                switch($specField->getFieldValue('type'))
                {
                    case SpecField::TYPE_NUMBERS_SIMPLE:
                        if(!isset($v['rangeStart']) || !is_numeric($v['rangeStart']) | !isset($v['rangeEnd']) || !is_numeric($v['rangeEnd']))
                        {
                            $errors['filters['.$key.'][rangeStart]'] = '_error_filter_value_is_not_a_number';
                        }
                    break;
                    case SpecField::TYPE_TEXT_DATE: 
                        if(
                                !isset($v['rangeDateStart'])
                             || !isset($v['rangeDateEnd']) 
                             || count($sdp = explode('-', $v['rangeDateStart'])) != 3 
                             || count($edp = explode('-', $v['rangeDateEnd'])) != 3
                             || !checkdate($edp[1], $edp[2], $edp[0]) 
                             || !checkdate($sdp[1], $sdp[2], $sdp[0])
                        ){
                            $errors['filters['.$key.'][date_range]'] = '_error_illegal_date';
                        }
                    break;
                }
                if(!isset($v['name'][$languageCodes[0]])) {
                print_r($v);
                echo $key;
                }
                if($v['name'][$languageCodes[0]] == '')
                {
                    $errors['filters['.$key.'][name]['.$languageCodes[0].']'] = '_error_filter_name_empty';
                }
            }
        }
        
        return $errors;
    }
}

?>