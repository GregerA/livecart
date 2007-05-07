<?php

ClassLoader::import("library.activerecord.ActiveRecord");
ClassLoader::import("application.model.*");
ClassLoader::import("application.model.locale.*");

ActiveRecord::$creolePath = ClassLoader::getRealPath("library");

include ClassLoader::getRealPath("storage.configuration.database") . '.php';
ActiveRecord::setDSN($GLOBALS['dsn']);
ActiveRecord::getLogger()->setLogFileName(ClassLoader::getRealPath("cache") . DIRECTORY_SEPARATOR . "activerecord.log");

/**
 * Base class for all ActiveRecord based models of application (single entry point in
 * application specific model class hierarchy)
 *
 * @package application.model
 */
abstract class ActiveRecordModel extends ActiveRecord
{	
	public function loadRequestData(Request $request)
	{
		$schema = ActiveRecordModel::getSchemaInstance(get_class($this));
		foreach ($schema->getFieldList() as $field)
		{
			if (!($field instanceof ARForeignKey || $field instanceof ARPrimaryKey))
			{
				$name = $field->getName();
				if ($request->isValueSet($name))
				{
					switch (get_class($field->getDataType()))
					{
						case 'ARArray':
							$this->setValueArrayByLang(array($name), Store::getInstance()->getDefaultLanguageCode(), Store::getInstance()->getLanguageArray(Store::INCLUDE_DEFAULT), $request);
						break;
								
						case 'ARBool':
							$this->setFieldValue($name, in_array($request->getValue($name), array('on', 1)));
						break;
							
						default:
							$this->setFieldValue($name, $request->getValue($name));	
						break;	
					}
				}
				else if('ARBool' == get_class($field->getDataType()))
				{
					if($this->getField($name)) $this->setFieldValue($name, 0);
				}
			}
		}	
	}
	
	protected static function transformArray($array, $className)
	{

    	$dateTransform = array
    	(		
    		'time_full' => Locale::FORMAT_TIME_FULL,
    		'time_long' => Locale::FORMAT_TIME_LONG,
    		'time_medium' => Locale::FORMAT_TIME_MEDIUM,
    		'time_short' => Locale::FORMAT_TIME_SHORT,
    		'date_full' => Locale::FORMAT_DATE_FULL,
    		'date_long' => Locale::FORMAT_DATE_LONG,
    		'date_medium' => Locale::FORMAT_DATE_MEDIUM,
    		'date_short' => Locale::FORMAT_DATE_SHORT,		
    	);
    

		foreach (self::getSchemaInstance($className)->getFieldsByType('ARDateTime') as $field)
		{
			$name = $field->getName();
			$time = strtotime($array[$name]);
			
			if (!$time)
			{
				continue;
			}
			
			if (!isset($locale))
			{
				$locale = Locale::getCurrentLocale();
			}
			
			$res = array();						
			foreach ($dateTransform as $format => $code)
			{
				$res[$format] = $locale->getFormattedTime($time, $code);
			}
				
			$array['formatted_' . $name] = $res;
	
	//	var_dump($res);
		}	
		
		return parent::transformArray($array, $className);
	}
}

?>