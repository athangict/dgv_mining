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

class TargetController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_login_id; 	// logined user id
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; // safedata controller plugin


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
	public function targetAction()
    {
        $this->init();
        $page = (int) $this->params()->fromRoute('page', 1);
        $itemsPerPage = 20;
        $machineTable = $this->getDefinedTable(Assessment\QtargetTable::class);
     // $paginator = new Paginator(new ArrayAdapter($machineTable->getAll()));

       // $paginator->setCurrentPageNumber($page);
       // $paginator->setItemCountPerPage($itemsPerPage);
//$paginator->setPageRange(8);

        return new ViewModel([
            'title'          => 'Step 1 - Machine Details',
            'page'           => $page,
            'itemsPerPage'   => $itemsPerPage,
            'target'        => $this->getDefinedTable(Assessment\QtargetTable::class)->getAll(),
        ]);
    }
	/**
	 *  ADD ASSET TYPE
	 */
	public function addtargetAction()
{
    $this->init();
    $page = $this->_id;

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        $data = array(
				'year'      => $form['year'],  
				'date' 		  => $form['date'],
				'status' 		=> 1,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Assessment\QtargetTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Asset Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Asset Type");
			endif;
			return $this->redirect()->toRoute('target', array('action'=>'target','page'=>$this->_id, 'id'=>'0'));
		}

    $ViewModel = new ViewModel([
        'title' => 'Add Vehicle',
        'page'  => $page,
    ]);

    $ViewModel->setTerminal(true);
    return $ViewModel;
}
}
