<?php

ClassLoader::import('application.model.datasync.api.reader.ApiReader');

/**
 * User model API XML format request parsing (reading/routing)
 *
 * @package application.model.datasync
 * @author Integry Systems <http://integry.com>
 */

class XmlUserApiReader extends ApiReader
{
	const HANDLE = 0;
	const CONDITION = 1;
	const ALL_KEYS = -1;
	//protected $xmlKeyToApiActionMapping = array(
		// 'filter' => 'list' filter is better than list, because list is keyword.
	//);
	private $apiActionName;
	private $listFilterMapping;

	public static function canParse(Request $request)
	{
		$get = $request->getRawGet();
		if(array_key_exists('xml',$get))
		{
			$xml = self::getSanitizedSimpleXml($get['xml']);
			if($xml != null)
			{
				if(count($xml->xpath('/request/customer')) == 1)
				{
					$request->set(ApiReader::API_PARSER_DATA ,$xml);
					$request->set(ApiReader::API_PARSER_CLASS_NAME, __CLASS__);
					return true;
				}
			}
		}
		return false;
	}

	public function populate($updater, $profile)
	{
		parent::populate($updater, $profile, $this->xml, 
			'/request/customer/[[API_ACTION_NAME]]/[[API_FIELD_NAME]]', array('ID','email'));
	}
	
	public function getARSelectFilter()
	{
		$arsf = new ARSelectFilter();
		
		$ormClassName = 'User';
		$filterKeys = $this->getListFilterKeys();

		foreach($filterKeys as $key)
		{
			$data = $this->xml->xpath('//filter/'.$key);
			while(count($data) > 0)
			{
				$z  = $this->getListFilterConditionAndARHandle($key);
				$value = (string)array_shift($data);
				$arsf->mergeCondition(
					new $z[self::CONDITION](
						$z[self::HANDLE],						
						$this->sanitizeFilterField($key, $value)
					)
				);
			}
		}
		return $arsf;
	}
	
	public function getListFilterMapping()
	{
		if($this->listFilterMapping == null)
		{
			$cn = 'User';
			$this->listFilterMapping = array(
				'id' => array(
					self::HANDLE => new ARFieldHandle($cn, 'ID'),
					self::CONDITION => 'EqualsCond'),
				'name' => array(
					self::HANDLE => new ARExpressionHandle("CONCAT(".$cn.".firstName,' ',".$cn.".lastName)"),
					self::CONDITION => 'LikeCond'),
				'first_name' => array(
					self::HANDLE => new ARFieldHandle($cn, 'firstName'),
					self::CONDITION => 'LikeCond'),
				'last_name' => array(
					self::HANDLE => new ARFieldHandle($cn, 'lastName'),
					self::CONDITION => 'LikeCond'),
				'company_name' => array(
					self::HANDLE => new ARFieldHandle($cn, 'companyName'),
					self::CONDITION => 'LikeCond'),
				'email' => array(
					self::HANDLE => new ARFieldHandle($cn, 'email'),
					self::CONDITION => 'LikeCond'),
				'created' => array(
					self::HANDLE => new ARFieldHandle($cn, 'dateCreated'),
					self::CONDITION => 'EqualsCond'),
				'enabled' => array(
					self::HANDLE => new ARFieldHandle($cn, 'isEnabled'),
					self::CONDITION => 'EqualsCond')
			);
		}
		return $this->listFilterMapping;
	}
	
	
	public function getListFilterConditionAndARHandle($key)
	{
		$mapping = $this->getListFilterMapping();
		if(array_key_exists($key, $mapping) == false || array_key_exists(self::CONDITION, $mapping[$key]) == false)
		{
			throw new Exception('Condition for key ['.$key.'] not found in mapping');
		}
		if(array_key_exists($key, $mapping) == false || array_key_exists(self::HANDLE, $mapping[$key]) == false)
		{
			throw new Exception('Handle for key ['.$key.'] not found in mapping');
		}

		return $mapping[$key];
	}
	
	public function getListFilterCondition($key)
	{
		$r = $this->getListFilterConditionAndARHandle($key);
		return $r[$key][self::CONDITION];
	}
	
	public function getListFilterARHandle($key)
	{
		$r = $this->getListFilterConditionAndARHandle($key);
		return $r[$key][self::HANDLE];
	}

	public function getListFilterKeys()
	{
		return array_keys($this->getListFilterMapping());
	}

	public function sanitizeFilterField($name, &$value)
	{
		switch($name)
		{
			case 'enabled':
				$value = in_array(strtolower($value), array('y','t','yes','true','1')) ? true : false;
				break;
		}
		return $value;
	}

	protected function findApiActionName($xml)
	{
		return parent::findApiActionNameFromXml($xml, '/request/customer');
	}

	public function loadDataInRequest($request)
	{
		$apiActionName = $this->getApiActionName();
		$shortFormatActions = array('get','delete'); // like <customer><delete>[customer id]</delete></customer>
		if(in_array($apiActionName, $shortFormatActions))
		{
			$request = parent::loadDataInRequest($request, '//', $shortFormatActions);
			$request->set('ID',$request->get($apiActionName));
			$request->remove($apiActionName);
		} else {
			$request = parent::loadDataInRequest($request, '/request/customer//', $this->getApiFieldNames());
		}
		return $request;
	}
}
