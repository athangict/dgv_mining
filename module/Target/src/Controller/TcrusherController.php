<?php
namespace Target\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Interop\Container\ContainerInterface;
use Target\Model As Target;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Stock\Model As Stock;
use Accounts\Model As Accounts;

class TcrusherController extends AbstractActionController
{   
private $_container;

protected $_table;          // database table
protected $_user;           // user detail
protected $_login_id;       // logged-in user id
protected $_login_role;     // logged-in user role
protected $_author;         // author id
protected $_created;        // created datetime
protected $_modified;       // modified datetime
protected $_config;         // configuration details
protected $_dir;            // default file directory
protected $_id;             // route parameter id
protected $_auth;           // authentication service
protected $_safedataObj;    // safedata controller plugin

// PHP 8.2
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
	public function tcrusherAction()
    {
        $this->init();
        $page = (int) $this->params()->fromRoute('page', 1);
        $itemsPerPage = 20;
        $machineTable = $this->getDefinedTable(Target\QtargetTable::class);
        // $paginator = new Paginator(new ArrayAdapter($machineTable->getAll()));

       // $paginator->setCurrentPageNumber($page);
       // $paginator->setItemCountPerPage($itemsPerPage);
      //$paginator->setPageRange(8);

        return new ViewModel([
            'title'          => 'Step 1 - Machine Details',
            'page'           => $page,
            'itemsPerPage'   => $itemsPerPage,
            'target'        => $this->getDefinedTable(Target\CrushertTable::class)->getAll(),
			'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
			'periodObj'      => $this->getDefinedTable(Target\MonthTable::class),
        ]);
    }
	/**
	 *  ADD ASSET TYPE
	 */
	public function addtcrusherAction()
{
    $this->init();
    $page = $this->_id;

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        $data = array(
            'year'      => $form['year'],
			'item'      => $form['item'],
            'period'      => $form['period'],
			'target'      => $form['target'],
            'status'    => 2,
            'author'    => $this->_author,
            'created'   => $this->_created,
            'modified'  => $this->_modified,
        );

        $data = $this->_safedataObj->rteSafe($data);

        $result = $this->getDefinedTable(Target\CrushertTable::class)->save($data);

        if ($result > 0) {
            $this->flashMessenger()->addMessage("success^ New Target successfully added");

            // Redirect to addtargetdtlsAction and pass the newly created target ID
            return $this->redirect()->toRoute('tcrusher', [
                'action' => 'tcrusher', 'id'   => $result, 'page'   => $this->_id ]);
        } else {
            $this->flashMessenger()->addMessage("Failed^ Failed to add new Target");
			
        }
    }

    $ViewModel = new ViewModel([
        'title'     => 'Add Target',
        'target_id' => $this->_id, 
        'page'      => $page,
		'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
		'periods'      => $this->getDefinedTable(Target\MonthTable::class)->getAll(),
    ]);

    $ViewModel->setTerminal(true);
    return $ViewModel;
}

public function edittargetAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$data = array(
            'id'        => $this->_id,
            'year'      => $form['year'],
			'item'      => $form['item'],
            'period'      => $form['period'],
			'target'      => $form['target'],
                'status'    => 2,
                'author'    => $this->_author,
                'modified'  => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Target\CrushertTable::class)->save($data);
			if($result > 0){ 
				$this->flashMessenger()->addMessage("success^ Successfully editted target :");
				return $this->redirect()->toRoute('target', array('action' =>'viewtcrusher', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Tender");
				return $this->redirect()->toRoute('target', array('action' =>'edittarget','id'=>$this->_id));
			}		
		}		
		return new ViewModel( array(
				'target'	=>  $this->getDefinedTable(Target\CrushertTable::class)->get($this->_id),			
		));		
	}

public function viewtcrusherAction(){
	$this->init();
	return new ViewModel(array(
		'id' => $this->_id,
		'targetObj' => $this->getDefinedTable(Target\CrushertTable::class)->get($this->_id),
		'targetDetails'  => $this->getDefinedTable(Target\CrushertTable::class)->get($this->_id),
		'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
		'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
		'periodObj'      => $this->getDefinedTable(Target\PeriodTable::class),
	));
}
public function commitAction(){
	$this->init();
	
	$data = array(
			'id'			=>$this->_id,
			'status' 		=> 4,
			'author'	    => $this->_author,
			'modified'      => $this->_modified,
	);
	$data   = $this->_safedataObj->rteSafe($data);
	$result = $this->getDefinedTable(Target\CrushertTable::class)->save($data);
	if($result):
		$this->flashMessenger()->addMessage("success^ Successfully commited ");
	else:
		$this->flashMessenger()->addMessage("error^ Failed to Commit");
	endif;
	return $this->redirect()->toRoute('tcrusher',array('action'=>'viewtcrusher','id'=>$this->_id));
	
}

}
