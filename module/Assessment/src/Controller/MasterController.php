<?php
namespace Assessment\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Interop\Container\ContainerInterface;
use Assessment\Model As Assessment;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Accounts\Model As Accounts;

class MasterController extends AbstractActionController
{   
	 protected $_container;
	protected $_table;
protected $_user;
protected $_login_id;
protected $_login_role;
protected $_author;
protected $_created;
protected $_modified;
protected $_config;
protected $_dir;
protected $_id;
protected $_auth;
protected $_safedataObj;

protected $_highest_role;
protected $_lowest_role;
protected $_connection;


	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
   /**
	 * Laminas Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}
	/**
	 * User defined Model
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefinedTable($table)
    {
        $definedTable = $this->_container->get($table);
        return $definedTable;
    }
	/**
	* initial set up
	* general variables are defined here
	*/
	public function init()
	{
		$this->_auth = new AuthenticationService;
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
			$this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		if(!isset($this->_config)) {
			$this->_config = $this->_container->get('Config');
		}
		if(!isset($this->_user)) {
		    $this->_user = $this->identity();
		}
		if(!isset($this->_login_id)){
			$this->_login_id = $this->_user->id;  
		}
		if(!isset($this->_login_role)){
			$this->_login_role = $this->_user->role;  
		}
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column='id');  
		}
		if(!isset($this->_lowest_role)){
			$this->_lowest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMin($column='id'); 
		}
		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}
		
		$this->_id = $this->params()->fromRoute('id');
		
		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		

		$fileManagerDir = $this->_config['file_manager']['dir'];
	
		if(!is_dir($fileManagerDir)) {
			mkdir($fileManagerDir, 0777);
		}	

		$this->_dir =realpath($fileManagerDir);
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}

	/**
	 *  Vehicle TYPE 
	 */
	public function companyAction()
	{
		$this->init();
		$assetmgtTable = $this->getDefinedTable(Assessment\HiringcompanyTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetmgtTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'       => 'Vehicle Master',
			'paginator'   => $paginator,
			'page'        => $page,
		));
	} 
	/**
	 *  ADD ASSET TYPE
	 */
	public function addcompanyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
			$max_code = $this->getDefinedTable(Assessment\HiringcompanyTable::class)->getMax('company_code');
			$max_code = $max_code+1;
			$num_length = strlen((string)$max_code);
			if($num_length == 1){$max_code = '0'.$max_code;}
            $data = array(
				'company_code'      => $max_code,  
				'company' 		  => $form['company'],
				'status' 		=> 1,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\HiringcompanyTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Asset Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Asset Type");
			endif;
			return $this->redirect()->toRoute('masters', array('action'=>'company','page'=>$this->_id, 'id'=>'0'));
		}
		$ViewModel = new ViewModel(array(
		'title' => 'Add Company',

		
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * editdistrict action
	 */
	public function editcompanyAction()
	{
		// $data = $this->getDefinedTable(Assessment\HiringcompanyTable::class)->get($company_id);
		// echo'<pre>';print_r($data);exit;
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$company_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(  
				'id'                 => $form['company_id'],
				'company'           => $form['company'],
				'status'             => $form['status'],
				'author'             => $this->_author,
				'modified'           => $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();
            $result = $this->getDefinedTable(Assessment\HiringcompanyTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ successfully edited district");
            else:
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to edit district");
            endif;
			return $this->redirect()->toRoute('masters', array('action'=>'company','page'=>$this->_id, 'id'=>'0'));
        }		
		$ViewModel = new ViewModel([
				'title'    => 'Edit District',
				'page'     => $page,
				'companys'=> $this->getDefinedTable(Assessment\HiringcompanyTable::class)->get($company_id),
		]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}


/**  
	 * Vehicle action
	 */
	public function vehicleAction()
	{	
		$this->init();
		$company_id = (isset($this->_id))? $this->_id:'-1';
		$vehicelTable = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->getColumnValue('company',$company_id);
		//$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($vehicelTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
//$paginator->setCurrentPageNumber((int)$page);
		//$paginator->setItemCountPerPage(20);
		//$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'         => 'Vehicle',
			'vehicelTable'     => $vehicelTable,
			'company_id'   => $company_id,
			'page'          => $page,
			'companyObj'   => $this->getDefinedTable(Assessment\HiringcompanyTable::class),
		)); 
	} 
	/**
	 *  Add Vehicle action
	 */
	public function addvehicleAction()
	{
		$this->init();
		$page = $this->_id;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$max_code = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->getMax('vehicle_code');
			$max_code = $max_code+1;
			$num_length = strlen((string)$max_code);
			switch($num_length):
				case 1:
					$max_code = '00'.$max_code;
					break;
				case 2:
					$max_code = '0'.$max_code;
					break;
				default:
					$max_code = $max_code;
					break;
			endswitch;
			$data = array(	
					'company'     => $form['company'],
					'vehicle_code'   => $max_code,
					'vehicle'        => $form['vehicle'],
					'status'       => $form['status'],
					'author'       => $this->_author,
					'created'      => $this->_created,
					'modified'     => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->save($data);	
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new block.");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new block.");
			endif;
			return $this->redirect()->toRoute('masters',array('action'=>'vehicle','page'=>$this->_id, 'id'=>$form['company']));
						 
		}
		$ViewModel = new ViewModel(array(
				'title'      => 'Add Vehicel',
				'page'       => $page,
				'companys'  => $this->getDefinedTable(Assessment\HiringcompanyTable::class)->get(array('status'=>'1')),	
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	/**
	 *  Edit Block action
	 */
	public function editvehicleAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$vehicle_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
				'id'           => $form['vehicle_id'],
				'company'     => $form['company'],
				'vehicle'        => $form['vehicle'],
				'status'       => $form['status'],
				'author'       => $this->_author,
				'modified'     => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->save($data);	
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully edited block.");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to edited block.");
			endif;
			return $this->redirect()->toRoute('masters',array('action'=>'vehicle','page'=>$this->_id, 'id'=>$form['company']));
		}
		$ViewModel = new ViewModel(array(
				'title'      => 'Edit Vehicle',
				'page'       => $page,
				'companys'  => $this->getDefinedTable(Assessment\HiringcompanyTable::class)->get(array('status'=>'1')),	
				'vehicles'     => $this->getDefinedTable(Assessment\HiringvehicleTable::class)->get($vehicle_id),
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}

		/**
	 *  Explosive TYPE 
	 */
	public function explosivetypeAction()
	{
		$this->init();
		$assetmgtTable = $this->getDefinedTable(Assessment\MasterexplosiveTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetmgtTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'       => 'Vehicle Master',
			'paginator'   => $paginator,
			'page'        => $page,
			
		));
	} 

		/**
	 *  ADD ASSET TYPE
	 */
	public function addexplosivetypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'code' 			      => $form['code'],
				'explosive_type'      => $form['explosive_type'],
				'specification'       => $form['specification'],
				'unit_of_measurement' => $form['unit_of_measurement'],
				'status' 		      => 1,
				'author' 		      =>$this->_author,
				'created' 		      =>$this->_created,
				'modified' 		      =>$this->_modified,
			);
			//echo'<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\MasterexplosiveTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Asset Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Asset Type");
			endif;
			return $this->redirect()->toRoute('masters', array('action'=>'explosivetype'));
		}
		$ViewModel = new ViewModel(array(
	
		
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 *  ADD ASSET TYPE
	 */
	public function addlocationAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(
				'location' 		  => $form['location'],
				'status' 		=> 1,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\DispatcherlocationTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Asset Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Asset Type");
			endif;
			return $this->redirect()->toRoute('masters', array('action'=>'location','page'=>$this->_id, 'id'=>'0'));
		}
		$ViewModel = new ViewModel(array(
		'title' => 'Add Location',

		
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**  
	 * Location action
	 */
	public function locationAction()
	{
		$this->init();
		$assetmgtTable = $this->getDefinedTable(Assessment\DispatcherlocationTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetmgtTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'       => ' Master Location',
			'paginator'   => $paginator,
			'page'        => $page,
			
		));
	} 
	
	
	/**
	 *  ADD ASSET TYPE
	 */
	public function addworkcategoryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(
				'category' 		  => $form['category'],
				'status' 		=> 1,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\WorkcategoryTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Asset Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Asset Type");
			endif;
			return $this->redirect()->toRoute('masters', array('action'=>'location','page'=>$this->_id, 'id'=>'0'));
		}
		$ViewModel = new ViewModel(array(
		'title' => 'Add Location',

		
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**  
	 * Location action
	 */
	public function workcategoryAction()
	{
		$this->init();
		$assetmgtTable = $this->getDefinedTable(Assessment\WorkcategoryTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assetmgtTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title'       => ' Master Location',
			'paginator'   => $paginator,
			'page'        => $page,
			
		));
	} 
}
