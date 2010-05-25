<?php
ClassLoader::import('application.model.datasync.ModelApi');
ClassLoader::import('application.model.datasync.api.reader.XmlUserApiReader');
ClassLoader::import('application/model.datasync.CsvImportProfile');
		
/**
 * Web service access layer for User model
 *
 * @package application.model.datasync
 * @author Integry Systems <http://integry.com>
 * 
 */

class UserApi extends ModelApi
{
	private $listFilterMapping = null;
	protected $application;


	public static function canParse(Request $request)
	{
		return parent::canParse($request, array('XmlUserApiReader'));
	}

	public function __construct(LiveCart $application)
	{
		parent::__construct(
			$application,
			'User',
			array_keys(User::getNewInstance('')->getSchema()->getFieldList())
		);
	}

	// ------ 

	public function create()
	{
		$updater = new ApiUserImport($this->application);
		$updater->allowOnlyCreate();
		$profile = new CsvImportProfile('User');
		$reader = $this->getDataImportIterator($updater, $profile);
		$updater->setCallback(array($this, 'userImportCallback'));
		$updater->importFile($reader, $profile);

		return $this->statusResponse($this->importedIDs, 'created');
	}
	
	public function update()
	{
		$updater = new ApiUserImport($this->application);
		$updater->allowOnlyUpdate();
		$profile = new CsvImportProfile('User');
		$reader = $this->getDataImportIterator($updater, $profile);
		$updater->setCallback(array($this, 'userImportCallback'));
		$updater->importFile($reader, $profile);

		return $this->statusResponse($this->importedIDs, 'updated');
	}

	public function delete()
	{
		$request = $this->getApplication()->getRequest();
		$id = $this->getRequestID();
		$instance = User::getInstanceByID($id, true);
		$instance->delete();
		return $this->statusResponse($id, 'deleted');
	}

	public function get()
	{
		$request = $this->application->getRequest();
		$parser = $this->getParser();
		$users = ActiveRecordModel::getRecordSetArray('User',
			select(eq(f('User.ID'), $this->getRequestID()))
		);
		if(count($users) == 0)
		{
			throw new Exception('User not found');
		}
		$apiFieldNames = $parser->getApiFieldNames();

		// --
		$response = new SimpleXMLElement('<response datetime="'.date('c').'"></response>');
		$responseCustomer = $response->addChild('customer');
		while($user = array_shift($users))
		{
			foreach($user as $k => $v)
			{
				if(in_array($k, $apiFieldNames))
				{
					$responseCustomer->addChild($k, $v);
				}
			}
		}
		return new SimpleXMLResponse($response);
	}

	public function filter()
	{
		$parser = $this->getParser();
		$customers = User::getRecordSet($parser->getARSelectFilter());
		$response = new SimpleXMLElement('<response datetime="'.date('c').'"></response>');
		while($customer = $customers->shift())
		{
			$customerNode = $response->addChild('customer');
			$customerNode->addChild('custno', $customer->getID());
			$ormXmlAddressMapping = array(
				'address1'=>'address_1',
				'address2'=>'address_2',
				'city'=>'city',
				'stateName'=>'state_name',
				'postalCode'=>'postal_code',
				'phone'=>'phone'
			);
			$customerNode->addChild('name', $customer->firstName->get(),' '.$customer->lastName->get());
			foreach(array('billing', 'shipping') as $z)
			{
				$mn = 'get'.ucfirst($z).'AddressArray'; // getBillingAddressArray() or getShippingAddressArray()
				foreach($customer->$mn() as $a)
				{
					foreach($ormXmlAddressMapping as $addressOrmKey=>$addressXmlKey)
					{
						$customerNode->addChild($z.'_'.$addressXmlKey,$a['UserAddress'][$addressOrmKey]);
					}
				}
			}
		}
		return new SimpleXMLResponse($response);
	}

	// ------ 
	
	public function userImportCallback($record)
	{
		$this->importedIDs[] = $record->getID();
	}

	private function getDataImportIterator($updater, $profile)
	{
		// parser can act as DataImport::importFile() iterator
		$parser = $this->getParser();
		$parser->populate($updater, $profile);
		return $parser;
	}
}

ClassLoader::import("application.model.datasync.import.UserImport");
// misc things
// @todo: in seperate file!
class ApiUserImport extends UserImport
{
	const CREATE = 1;
	const UPDATE = 2;
	
	private $allowOnly = null;

	public function allowOnlyUpdate()
	{
		$this->allowOnly = self::UPDATE;
	}

	public function getClassName()  // because dataImport::getClassName() will return ApiUser, not User.
	{
		return 'User';
	}

	public function allowOnlyCreate()
	{
		$this->allowOnly = self::CREATE;
	}



	public // one (bad) implementation of delete() action calls this method, therefore public
	function getInstance($record, CsvImportProfile $profile)
	{
		$instance = parent::getInstance($record, $profile);
		
		
		$id = $instance->getID();
		if($this->allowOnly == self::CREATE && $id > 0) 
		{
			throw new Exception('Record exists');
		}
		if($this->allowOnly == self::UPDATE && $id == 0) 
		{
			throw new Exception('Record not found');
		}
		return $instance;
	}

}
?>
