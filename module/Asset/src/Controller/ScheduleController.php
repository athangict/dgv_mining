<?php
namespace Asset\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Interop\Container\ContainerInterface;
use Asset\Model As Asset;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;

class ScheduleController extends AbstractActionController
{   
	  private $_container;

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
	/** ---------ASSET SCHEDULE-------------------------------------------- */
	public function scheduleAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$start_date  = date('Y-m-d');
			$end_date   = date('Y-m-d');
		endif;
		$data = array(
			'start_date' => $start_date,
			'current_date'  => $end_date,
		);
		//echo '<pre>';print_r($data);exit;
		return new ViewModel(array(
			'title'    			=> 'Assets Schedule',
			'data'				=> $data,
			'assetObj' 			=> $this->getDefinedTable(Asset\AssetmanagementTable::class),
			'assettypeObj' 		=> $this->getDefinedTable(Asset\AssettypeTable::class),
			'depreciationObj'	=> $this->getDefinedTable(Asset\DepreciationTable::class),
		));
	} 
}
