<?php
namespace Assessment\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Assessment\Model As Assessment; 
use Acl\Model As Acl;
use Hr\Model As Hr;
use Asset\Model As Asset;
use Stock\Model As Stock;
use Fleet\Model As Fleet;
use Administration\Model As Administration;

class AssessmentController extends AbstractActionController
{
    private $_container;

protected $_table;
protected $_user;

protected $_login_id;
protected $_login_role;
protected $_userloc;

protected $_author;
protected $_created;
protected $_modified;

protected $_config;
protected $_dir;
protected $_id;
protected $_auth;

protected $_safedataObj;
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
		$this->_config = $this->_container->get('Config');
		
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id; 
        $this->_userloc = $this->_user->location; 
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		//$this->_dir =realpath($fileManagerDir);

		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}

	/** 
	*IndexAction
	**/
	public function indexAction()
	{
		$this->init();		
			
		return new ViewModel( array(
				'title' => "Quarry Assessment",
		));   
	}

	/**
	 * Create AssessmentActions
	 **/
	public function assessmentAction()
	{
		$this->init();
		$page = (int) $this->params()->fromRoute('page', 1); 
		$itemsPerPage = 20;
		$assessmentTable = $this->getDefinedTable(Assessment\AssessmentTable::class);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($assessmentTable->getAll()));
	

		$paginator->setCurrentPageNumber($page);
		$paginator->setItemCountPerPage($itemsPerPage);
		$paginator->setPageRange(8);
	

		return new ViewModel([
			'title' => 'Step 1 - Infrastructure Details',
			'paginator'      => $paginator, 
			'page'           => $page,        
			'itemsPerPage'   => $itemsPerPage,
			'assessment'     =>$this->getDefinedTable(Assessment\AssessmentTable::class)->getAll(),
			'category' => $this->getDefinedTable(Assessment\WorkcategoryTable::class)

		]);
	}

	/**
	 * Add AssessmentAction
	 **/
	public function addassessmentAction()
	{   
		$this->init();
		// $location =$this->_login_location;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$location_code = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_login_location,'location_code');
			$app_no_data = array(
				'service_code'  =>'A',
			    'location_code' => $location_code,
				'date'          => $this->_created,
				'column'        => 'application_no',
				'tableObj'      => $this->getDefinedTable(Assessment\AssessmentTable::class),
			);
			$application_no = $this->generateApplicationNo($app_no_data);
			$data = [
				'application_no'     =>$application_no,
			 	'date'		         =>$form['date'],
				'category'           => $form['categoryy'],
				'author' 	  	     => $this->_author,
	 		    'created'	  	     => $this->_created,
				'maxstep' 			 => 1,
				'status'             => 2,
				'author'  		     => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\AssessmentTable::class)->save($data);
			if($result){
				$flow_result = $this->flowinitiation('1309', $result);
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Added Applicant Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id'=>$result));
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to add Applicant Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'addassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'                => 'Add New Application',
			'application_id'       => $this->_id,
			'esec' => $this->getDefinedTable(Assessment\AssessmentTable::class)->get($this->_id),
			'categories'            =>$this->getDefinedTable(Assessment\WorkcategoryTable::class)->getAll(),


		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**** generate unigue Id ***/
	public function generateApplicationNo($data)
	{
		$service_code = $data['service_code'];
		//  $location_code = $data['location_code'];
		$year = date('y', strtotime($data['date']));  
		$month = date('m', strtotime($data['date']));
		$prefix_no = $service_code . $year . $month;
		$column = $data['column'];
		$tableObj = $data['tableObj'];
		$results = $tableObj->getApplicationNo($prefix_no, $column);
		if (!$results) {
			$results = [];
		}
	
		$array_list = [];
		foreach ($results as $result) {
			array_push($array_list, substr($result[$column], 7)); 
		}
	
		$next_serial = (count($array_list) > 0) ? max($array_list) + 1 : 1;
		switch (strlen($next_serial)) {
			case 1: $next_tr_serial = "0000" . $next_serial; break;
			case 2: $next_tr_serial = "000" . $next_serial; break;
			case 3: $next_tr_serial = "00" . $next_serial; break;
			case 4: $next_tr_serial = "0" . $next_serial; break;
			default: $next_tr_serial = $next_serial; break;
		}
	
		return $application_no = $prefix_no . $next_tr_serial;
	}
	/** 
	 *  Edit AssessmentAction
	**/
	public function editassessmentAction()
	{   
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [
				'id'                        => $form['application_id'],
				'date'		  =>$form['date'],
				'category'     => $form['categoryy'],
				'author' 	  	  => $this->_author,
			    'created'	  	  => $this->_created,
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\AssessmentTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Edited Applcation.");
				return $this->redirect()->toRoute('q', array('action'=>'step1', 'id' =>$result));
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Edit Applcation.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'                => 'Edit Applications',
			'application_id'       => $this->_id, 
			'assessmentdetails'     =>$this->getDefinedTable(Assessment\AssessmentTable::class)->get($this->_id),
			'categories'            =>$this->getDefinedTable(Assessment\WorkcategoryTable::class)->getAll(),
			
		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	 *  Step 1 overview 
	 *  @Assignment Process 
	 */
	public function step1Action()
	{
		$this->init();
		$application_id = $this->_id; 
	
		if (!$application_id) {
			$this->flashMessenger()->addMessage("error^ Invalid Application ID.");
			return $this->redirect()->toRoute('someRoute');
		}

		$application = $this->getDefinedTable(Assessment\AssessmentTable::class)->get($this->_id);
		$applications = $this->getDefinedTable(Assessment\InfrastructureTable::class)->getByAssessmentId($this->_id);
		$explosives = $this->getDefinedTable(Assessment\ExplosiveTable::class)->getByAssessmentId($this->_id);
		$vechileassign = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->getByAssessmentId($this->_id);
		
	
		return new ViewModel([
			'title'          => 'Step 1 - Infrastructure Details',
			'application'    => $application,
			'applications'   => $applications,
			'explosives'     => $explosives,
			'vechileassign'     => $vechileassign,
			'application_id' => $application_id,
			'company' => $this->getDefinedTable(Assessment\HiringcompanyTable::class),
			'excabatorObj'  => $this->getDefinedTable(Asset\AssetmanagementTable::class),
			'employeeObj'  => $this->getDefinedTable(Hr\EmployeeTable::class),
			'category' => $this->getDefinedTable(Assessment\WorkcategoryTable::class),
			'vechObj' => $this->getDefinedTable(Assessment\HiringvehicleTable::class),
			'expObj' => $this->getDefinedTable(Assessment\MasterexplosiveTable::class),
			'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)
			
		]);
	}
	/**
	 *  Application overview -- Direct to Step by Step
	 *  @Application Process 
	 **/
	public function overviewAction()
	{
		$this->init();
		$params = explode("-", $this->_id);
		$application_id =  $params['0'];
		if(sizeof($params)>1) {if($params['1'] > 0){
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['1'], 'flag');
			if($flag == "0") {
				$notify = array('id' => $params['1'], 'flag'=>'1');
               	$this->getDefinedTable(Acl\NotifyTable::class)->save($notify);
			}
		}}
		$step = $this->getDefinedTable(Assessment\AssessmentTable::class)->getColumn($application_id,'maxstep');
		switch($step){
			case 1: return $this->redirect()->toRoute('q', array('action'=>'step1', 'id'=>$application_id));
					break;
			case 2: return $this->redirect()->toRoute('q', array('action'=>'step2', 'id'=>$application_id));
					break;
			case 3: return $this->redirect()->toRoute('q', array('action'=>'step3', 'id'=>$application_id));
					break;
			case 4: return $this->redirect()->toRoute('q', array('action'=>'step4', 'id'=>$application_id));
					break;
			default: return $this->redirect()->toRoute('q', array('action'=>'step4', 'id'=>$application_id));
					break;
		}
	}
	/** 
	 *  Add Infrastructure  Action
	 **/
	public function addinfrastructureAction()
	{   
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [
				'assessment_id'      => $form['application_id'],
				'excavator'         => $form['excavator'],
				'driver_name'        => $form['driver_name'],
				'start_meter'        => $form['start_meter'],
				'end_meter'         => $form['end_meter'],
				'hsd_issued'         => $form['hsd_issued'],
				'author'  		     => $this->_author,
				'created'            => $this->_created,
				'modified'           => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\InfrastructureTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Added Infrastructure Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id'=>$data['assessment_id']));
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to add Infrastructure Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'                => 'Add Infrastructure Details',
			'application_id'       => $this->_id, 
				'asset' => $this->getDefinedTable(Asset\AssetmanagementTable::class)->getAll(),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),


		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	

	public function getvehicleAction()
	{		
		$form = $this->getRequest()->getPost();
		$vehicleId = $form['vehicleId'];
		$vehicledtl = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->get(array('company'=>$vehicleId));	 
		$vehicle = "<option value=''></option>";
		foreach($vehicledtl as $vehicledtls){
			$vehicle.="<option value='".$vehicledtls['id']."'>". $vehicledtls['vehicle']."</option>";
		}
		echo json_encode(array(
				'vehicle' => $vehicle,
		));
		exit;
	}


	/** 
	 *  Edit EditinfrastructureAction  Action
	 **/
	public function editinfrastructureAction()
	{   
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$assid=$this->getDefinedTable(Assessment\InfrastructureTable::class)->getColumn($id,'assessment_id');
		//echo'<pre>';print_r($assid);exit;
		$infa_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			
			$data = [
				'assessment_id'         => $form['application_id'],
				'id'           => $form['infa_id'],
				'driver_name'        => $form['driver_name'],
				'start_meter'        => $form['start_meter'],
				'end_meter'         => $form['end_meter'],
				'hsd_issued'         => $form['hsd_issued'], 
				'author'  		        => $this->_author,
				'modified'              => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\InfrastructureTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Edited Infrastructure Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id' =>  $form['application_id'])); 
				
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Edit Infrastructure Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		
		$ViewModel = new ViewModel([
			'title'                => 'Edit Infrastructure Details',
			'id'               =>$this->_id,
			'application_id'   =>$assid,
			'infrastructuredetails'     =>$this->getDefinedTable(Assessment\InfrastructureTable::class)->get($this->_id),
			'infa'       =>$this->getDefinedTable(Assessment\InfrastructureTable::class)->get($assid),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),
			'asset' => $this->getDefinedTable(Asset\AssetmanagementTable::class)->getAll(),
		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	
/**
 * Edit updateweighbridgeAction
 */
public function updatemeterAction()
{
    $this->init();
    $id = $this->_id;
    $array_id = explode("_", $id);
    $assid = $this->getDefinedTable(Assessment\InfrastructureTable::class)->getColumn($id, 'assessment_id');
    $page = (sizeof($array_id) > 1) ? $array_id[1] : '';

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        $data = array(
            'assessment_id' => (int)$form['application_id'], 
            'id'            => (int)$form['infa_id'],
            'end_meter'        => (float)$form['end_meter'], 
			'hsd_issued'         => (float)$form['hsd_issued'],
            'author'        => $this->_author,
            'created'       => $this->_created,
            'modified'      => $this->_modified, 
        );

        $this->_connection->beginTransaction();


        $result = $this->getDefinedTable(Assessment\InfrastructureTable::class)->save($data);

        if ($result) {
            $this->_connection->commit();
            $this->flashMessenger()->addMessage("success^ Successfully Updated Weighbridge");
            return $this->redirect()->toRoute('q', array('action' => 'step1', 'id' => $form['application_id']));
        } else {
            $this->_connection->rollback();
            $this->flashMessenger()->addMessage("error^ Failed to Update.");
            return $this->redirect()->toRoute('q/paginator', array('action' => 'step1', 'page' => $assid, 'id' => '0'));
        }
    }

    $ViewModel = new ViewModel([
        'title'          => 'Update End Meter Details',
        'id'             => $this->_id,
        'application_id' => $assid,
        'extrantions'    => $this->getDefinedTable(Assessment\InfrastructureTable::class)->get($this->_id),
        'assesments'     => $this->getDefinedTable(Assessment\InfrastructureTable::class)->get($assid),
        //'vehicleObj'     => $this->getDefinedTable(Assessment\HiringvehicleTable::class),
    ]);

    $ViewModel->setTerminal(true);
    return $ViewModel;
}




	/** 
    *Add Addexplosive  Action
	 **/
	 public function addexplosiveAction()
	 {	
		 $this->init();
		 if($this->getRequest()->isPost())
		 {
			 $form = $this->getRequest()->getpost();
			 $data = array(
				'assessment_id'          => $form['application_id'],
				'no_ofexplosive'		 =>$form['no_ofexplosive'],
				'explosive_name'		 =>$form['explosive_name'],
				'blasters'               => $form['blasters'],
				'author' 	  	         => $this->_author,
				'created'	  	         => $this->_created,
				'modified'               => $this->_modified
			 );
			// echo'<pre>';print_r($data);exit;
			 $this->_connection->beginTransaction();
			 $result = $this->getDefinedTable(Assessment\ExplosiveTable::class)->save($data);
			 if($result){
				 $this->_connection->commit(); 
				 $this->flashMessenger()->addMessage("success^ Successfully Added Infrastructure Details."); 	             
				 return $this->redirect()->toRoute('q', array('action' => 'step1', 'id'=>$data['assessment_id']));
			 }else {
				 $this->_connection->rollback();
				 $this->flashMessenger()->addMessage("error^ Failed to add Infrastructure Details.");
				 return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			 }
		 }
		 $ViewModel = new ViewModel([
			 'title'                => 'Add Explosive',
			 'application_id'       => $this->_id, 
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),
		 'explosive'     =>$this->getDefinedTable(Assessment\MasterexplosiveTable::class)->getAll(), 
		 ]);	
		 $ViewModel->setTerminal(True);
		 return $ViewModel;
	 }
/** 
	 *  Edit editexplosiveAction  
	 **/
	public function editexplosiveAction()
	{   
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$assid=$this->getDefinedTable(Assessment\ExplosiveTable::class)->getColumn($id,'assessment_id');
		$expo_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			
			$data = [
				'id'           => $form['expo_id'],
				'assessment_id'         => $form['application_id'],
				'no_ofexplosive'		  =>$form['no_ofexplosive'],
				'explosive_name'		  =>$form['explosive_name'],
				'blasters'                => $form['blasters'],
				'author'  		        => $this->_author,
				'modified'              => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\ExplosiveTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Edited Infrastructure Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id' =>  $form['application_id'])); 
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Edit Infrastructure Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'               => 'Edit Explosive Details',
			'id'                  =>$this->_id,
			'application_id'      =>$assid,
			'explosivedet'        =>$this->getDefinedTable(Assessment\ExplosiveTable::class)->get($this->_id),
			'assessmentdetails'   =>$this->getDefinedTable(Assessment\ExplosiveTable::class)->get($assid),
			'employees'           => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),
			'explosive'           =>$this->getDefinedTable(Assessment\ExplosiveTable::class), 

		]);
		
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	 *  ADD Assign vehicle 
	 */
	public function addassignvehicleAction()
	{	
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getpost();
			$data = array(
				'assessment_id'      => $form['application_id'],
				'company' 		     => $form['company'],
				'vehicle' 		     => $form['vehicle'],
				'author' 	  	     => $this->_author,
				'created'	  	     => $this->_created,
			   
			);
		   // echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Assigned Trucks ."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id'=>$data['assessment_id']));
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Assign Truck .");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'                => 'Add Explosive',
			'application_id'       => $this->_id, 
		    'companys'        =>$this->getDefinedTable(Assessment\HiringcompanyTable::class)->getAll(),

		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}



	public function getvehiclessAction()
	{		
		$form = $this->getRequest()->getPost();
		$vehicleId = $form['vehicleId'];
		$vehicledtl = $this->getDefinedTable(Assessment\HiringvehicleTable::class)->get(array('company'=>$vehicleId));

        $vehicle = "<option value='-1'>All</option>";
		foreach($vehicledtl as $row1):
			$vehicle.="<option value='".$row1['id']."'>".$row1['vehicle']."</option>";
		endforeach;
		echo json_encode(array(
				'vehicle' => $vehicle,
		));
		exit;
	}


	/** 
	 *  Edit editassignvehicleAction
	 **/
	public function editassignvehicleAction()
	{   
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$assid=$this->getDefinedTable(Assessment\TruckassignmentTable::class)->getColumn($id,'assessment_id');
		$infa_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [


                'assessment_id'         => $form['application_id'],
				'id'                 => $form['assign_id'],
				'company' 		     => $form['company'],
				'vehicle' 		     => $form['vehicle'],

				'author'  		          => $this->_author,
				'modified'                => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Edited Explosive Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step1', 'id' =>  $form['application_id'])); 
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Edit Explosive Details.");
				return $this->redirect()->toRoute('q', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'                => 'Edit Explosive Details',
			'id'               =>$this->_id,
			'application_id'   =>$assid,
			'assign'     =>$this->getDefinedTable(Assessment\TruckassignmentTable::class)->get($this->_id),
			'assigns'       =>$this->getDefinedTable(Assessment\TruckassignmentTable::class)->get($assid),
			'companys'        =>$this->getDefinedTable(Assessment\HiringcompanyTable::class)->getAll($this->_id),

		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	 * Step 2 -- Attendence DETAILS
	  */
	public function step2Action()
	{
		$this->init();
		$application_id = $this->_id;
		$maxstep = $this->getDefinedTable(Assessment\AssessmentTable::class)->getColumn($application_id,'maxstep');
		if($maxstep < 2):
			$this->getDefinedTable(Assessment\AssessmentTable::class)->save(array('id'=>$application_id,'maxstep'=>'2'));
		endif;
		$applications = $this->getDefinedTable(Assessment\AssessmentTable::class)->get($application_id);
		
		$applicationTable = $this->getDefinedTable(Assessment\AssessmentTable::class)->get(['id'=>$application_id]);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($applicationTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		
		return new ViewModel([
			'title'               => 'Step 2- Extraction',
			'login_id'            =>$this->_login_id,
			// 'paginator'        => $paginator,
			'application'         =>$applications,
			'page'      	      => $page,
			'attendence'          =>$this->getDefinedTable(Assessment\AttendanceTable::class)->getByAssessmentId($this->_id),
			'attendancObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'category' => $this->getDefinedTable(Assessment\WorkcategoryTable::class),
			'statusObj' => $this->getDefinedTable(Assessment\StatusTable::class),
			'empObj' => $this->getDefinedTable(Hr\EmployeeTable::class)
		]);
	}                      
	 /**
	  * Step 3 -- Tips
	 **/
	public function step3Action()
	{
		$this->init();
		$application_id = $this->_id;
		//echo'<pre>';print_r($application_id);exit;
		$maxstep = $this->getDefinedTable(Assessment\AssessmentTable::class)->getColumn($application_id,'maxstep');
		if($maxstep < 3):
			$this->getDefinedTable(Assessment\AssessmentTable::class)->save(array('id'=>$application_id,'maxstep'=>'3'));
		endif;
		$applications = $this->getDefinedTable(Assessment\AssessmentTable::class)->get($application_id);
		
		$applicationTable = $this->getDefinedTable(Assessment\AssessmentTable::class)->get(['id'=>$application_id]);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($applicationTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		$vechileassign = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->getByAssessmentId($this->_id);
		return new ViewModel([
			'title'               => 'Step 3- Trips',
			'login_id'            =>$this->_login_id,
			'application'         =>$applications,
			'page'      	      => $page,
			'vechileassign'       =>$vechileassign,
			'extractiondetails'   =>$this->getDefinedTable(Assessment\ExtractionTable::class)->getByAssessmentId($this->_id),
			'company'        =>$this->getDefinedTable(Assessment\HiringcompanyTable::class),
			'category' => $this->getDefinedTable(Assessment\WorkcategoryTable::class),
			'vechObj' => $this->getDefinedTable(Assessment\HiringvehicleTable::class),
			'dispatchtypeObj' => $this->getDefinedTable(Assessment\DispatchedTable::class),
			'locationObj' => $this->getDefinedTable(Assessment\DispatcherlocationTable::class),
		]);
	}
   /** 
	 *  Add AddAttendance  Action
   **/
	 public function addattendanceAction()
	 {   
		 $this->init();
		 if($this->getRequest()->isPost()){
			 $form = $this->getRequest()->getpost();
			 $data = [
				 'assessment_id'             => $form['application_id'],
				 'attendance_employee'       => $form['attendance_employee'],
				 'attendance_status'         => $form['attendance_status'],
				 'replacement_employee'      => $form['replacement_employee'],
				 'time'                      => $form['time'],
				 'remarks'                   => $form['remarks'],
				 'author'  		             => $this->_author,
				 'created'	  	             => $this->_created,
				 'modified'                  => $this->_modified
			 ];
			 //echo'<pre>';print_r($data);exit;
			 $this->_connection->beginTransaction();
			 $result = $this->getDefinedTable(Assessment\AttendanceTable::class)->save($data);
			 if($result){
				 $this->_connection->commit(); 
				 $this->flashMessenger()->addMessage("success^ Successfully Added Attendance Details."); 	             
				 return $this->redirect()->toRoute('q', array('action' => 'step2', 'id'=>$data['assessment_id']));
			 }else {
				 $this->_connection->rollback();
				 $this->flashMessenger()->addMessage("error^ Failed to add Attendance Details.");
				 return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			 }
		 }
			 $ViewModel = new ViewModel([
			 'title'                => 'Add Attendance Details',
			 'application_id'       => $this->_id, 
	         'bemployees'         => $this->getDefinedTable(Assessment\ExplosiveTable::class)->getByAssessmentId($this->_id),
			 'employees'          => $this->getDefinedTable(Assessment\InfrastructureTable::class)->getByAssessmentId($this->_id),
	        'emptObj'     =>$this->getDefinedTable(Hr\EmployeeTable::class),
			 'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),


		 ]);
		 $ViewModel->setTerminal(True);
		 return $ViewModel;
	 }
 /** 
	 *  Edit editattendance  Action
	 **/
	public function editattendanceAction()
	{   
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$assid=$this->getDefinedTable(Assessment\AttendanceTable::class)->getColumn($id,'assessment_id');
		$att_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [
				'assessment_id'         => $form['application_id'],
				'id'           => $form['att_id'],
				'attendance_employee'       => $form['attendance_employee'],
				'attendance_status'         => $form['attendance_status'],
				'replacement_employee'      => $form['replacement_employee'],
				'time'                      => $form['time'],
				'remarks'                   => $form['remarks'],
				'author' 	  	            => $this->_author,
			    'created'	  	            => $this->_created,
			];
		//echo'<pre>';print_r($data);exit;
		$this->_connection->beginTransaction();
		$result = $this->getDefinedTable(Assessment\AttendanceTable::class)->save($data);
		if($result){
			$this->_connection->commit(); 
			$this->flashMessenger()->addMessage("success^ Successfully Edited Infrastructure Details."); 	             
			return $this->redirect()->toRoute('q', array('action' => 'step2', 'id' =>  $form['application_id'])); 
		}else {
			$this->_connection->rollback();
			$this->flashMessenger()->addMessage("error^ Failed to Edit Infrastructure Details.");
			return $this->redirect()->toRoute('q', array('action'=>'step2','page'=>$this->_id, 'id'=>'0'));
		}
	}
		$ViewModel = new ViewModel([
			'title'                => 'Edit Attendance',
			'id'               =>$this->_id,
			'application_id'   =>$assid,
			'attendances'          => $this->getDefinedTable(Assessment\AttendanceTable::class)->get($this->_id), 
			'attendance'           => $this->getDefinedTable(Assessment\AttendanceTable::class)->get($assid), 
			'bemployees'           => $this->getDefinedTable(Assessment\ExplosiveTable::class)->get($this->_id), 
			'employees'                  => $this->getDefinedTable(Assessment\InfrastructureTable::class)->get($this->_id), 
			'emptObj'              =>$this->getDefinedTable(Hr\EmployeeTable::class),
			'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAllEmployee(),

		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}


	/** 
	 *  Add budgets  Action
	 */
	public function addextractionAction()
	{   
		$this->init();
	  //  $data = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->getByAssessmentId($this->_id);
	    
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [
				'assessment_id'             => $form['application_id'],
				'company' 		            => $form['company'],
				'vehicle' 		            => $form['vehicle'],
				'type_ofdispatche'          => $form['type_ofdispatche'],
				'aprox_weight'              => $form['aprox_weight'],
				'permit_no'                 => $form['permit_no'],
				'driver_name'                      => $form['driver_name'],
				'location'                      => $form['location'],
				'weight'                    => $form['weight'],
				'status'                    =>1,
				'author'  		            => $this->_author,
				'created'                   => $this->_created,
				'modified'                  => $this->_modified
			];
			//echo'<pre>';print_r($form['time'],);exit;
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\ExtractionTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Added Extraction Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step3', 'id'=>$data['assessment_id']));
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to add Extraction Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		//$test=$this->getDefinedTable(Assessment\TruckassignmentTable::class)->get(['assessment_id'=>$this->_id]);
		//echo '<pre>';print_r($test);exit;
		$ViewModel = new ViewModel([
			'title'                => 'Add Extraction Details',
			'application_id'       => $this->_id,
			'Dispatchs'     =>$this->getDefinedTable(Assessment\DispatchedTable::class)->getAll(),
			'companies'    =>$this->getDefinedTable(Assessment\TruckassignmentTable::class)->get(['assessment_id'=>$this->_id]),
			//'vehicle_no' => $this->getDefinedTable(Assessment\TruckassignmentTable::class)->getByAssessmentId($this->_id),
			'exreactObj' => $this->getDefinedTable(Assessment\HiringcompanyTable::class),
			'locationObj' => $this->getDefinedTable(Assessment\DispatcherlocationTable::class)->getAll(),
			
		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	
	public function getvehiclesAction()
	{		
		$form = $this->getRequest()->getPost();
		$companyID = $form['comID'];
		$assesmentId = $form['applicationId'];
		$vehicledtl = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->get(array('company'=>$companyID,'assessment_id'=>$assesmentId));	 
		
		
		$vehicle= "<option value=''></option>";
		foreach($vehicledtl as $vehicledtls){
			$vh_no=$this->getDefinedTable(Assessment\HiringvehicleTable::class)->getColumn($vehicledtls['vehicle'],'vehicle');
			$vehicle.="<option value='".$vehicledtls['vehicle']."'>".$vh_no."</option>";
		}
		echo json_encode(array( 
			'vehicle' => $vehicle,
		));
		exit;
	}

	public function getvehiclenoAction()
{
    $form = $this->getRequest()->getPost();
    $vehiclenoId = $form['vehiclenoId'];
    
    $vehicledt2 = $this->getDefinedTable(Assessment\TruckassignmentTable::class)->get(array('vehicle' => $vehiclenoId));
    
    $vehicle_no = '';
    if (!empty($vehicledt2)) {
        foreach ($vehicledt2 as $vehicledtls1) {
            $vehicle_no .= "<option value='" . $vehicledtls1['id'] . "'>" . htmlspecialchars($vehicledtls1['vehicle_no'], ENT_QUOTES, 'UTF-8') . "</option>";
        }
    } else {
        $vehicle_no = "<option value='' disabled>No vehicles found</option>";
    }
    
    echo json_encode(array('vehicle_no' => $vehicle_no));
    exit;
}


	/** 
	 *  Edit editextractionAction
	 **/
	public function editextractionAction()
	{   
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$assid=$this->getDefinedTable(Assessment\ExtractionTable::class)->getColumn($id,'assessment_id');
		$ext_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = [
				'assessment_id'         => $form['application_id'],
				'id'           => $form['ext_id'],
				'company' 		     => $form['company'],
				'vehicle' 		     => $form['vehicle'],
			
				'type_ofdispatche'          => $form['type_ofdispatche'],
				'aprox_weight'              => $form['aprox_weight'],
				'permit_no'                 => $form['permit_no'],
				'driver_name'                      => $form['driver_name'],
					'status'                    =>1,
				'author'  		            => $this->_author,
				'modified'                  => $this->_modified
			];
			//echo'<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Assessment\ExtractionTable::class)->save($data);
			if($result){
				$this->_connection->commit(); 
				$this->flashMessenger()->addMessage("success^ Successfully Added Extraction Details."); 	             
				return $this->redirect()->toRoute('q', array('action' => 'step3', 'id' =>  $form['application_id'])); 
			}else {
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to add Extraction Details.");
				return $this->redirect()->toRoute('q/paginator', array('action'=>'viewassessment','page'=>$this->_id, 'id'=>'0'));
			}
		}
		$ViewModel = new ViewModel([
			'title'             => 'Edit Extraction Details',
			'id'               =>$this->_id,
			'application_id'   =>$assid,
			'Extract'           => $this->getDefinedTable(Assessment\ExtractionTable::class)->get($this->_id), 
			'Extraction'        => $this->getDefinedTable(Assessment\ExtractionTable::class)->get($assid), 
			'dispatchObj'       =>$this->getDefinedTable(Assessment\DispatchedTable::class)->getAll(),
			'companys'          =>$this->getDefinedTable(Assessment\TruckassignmentTable::class)->get(['assessment_id'=>$this->_id]),
			'exreactObj'        => $this->getDefinedTable(Assessment\HiringcompanyTable::class)

		]);	
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
 /**
 * Edit updateweighbridgeAction
 */
public function updateweighbridgeAction()
{
    $this->init();
    $id = $this->_id;
    $array_id = explode("_", $id);
    $assid = $this->getDefinedTable(Assessment\ExtractionTable::class)->getColumn($id, 'assessment_id');
    $page = (sizeof($array_id) > 1) ? $array_id[1] : '';

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        $data = array(
            'assessment_id' => (int)$form['application_id'], // Needed only for ExtractionTable
            'id'            => (int)$form['extraction_id'],
            'weight'        => (float)$form['weight'], 
			'comments'        =>$form['comments'],// Ensure it's numeric
            'author'        => $this->_author,
            'created'       => $this->_created,
            'modified'      => $this->_modified, 
        );

        $this->_connection->beginTransaction();

        // Save data in the ExtractionTable
        $result = $this->getDefinedTable(Assessment\ExtractionTable::class)->save($data);

        if ($result) {
            $this->_connection->commit();
            $this->flashMessenger()->addMessage("success^ Successfully Updated Weighbridge");
            return $this->redirect()->toRoute('q', array('action' => 'step3', 'id' => $form['application_id']));
        } else {
            $this->_connection->rollback();
            $this->flashMessenger()->addMessage("error^ Failed to Update.");
            return $this->redirect()->toRoute('q/paginator', array('action' => 'step1', 'page' => $assid, 'id' => '0'));
        }
    }

    $ViewModel = new ViewModel([
        'title'          => 'Update Weighbridge Details',
        'id'             => $this->_id,
        'application_id' => $assid,
        'extrantions'    => $this->getDefinedTable(Assessment\ExtractionTable::class)->get($this->_id),
        'assesments'     => $this->getDefinedTable(Assessment\ExtractionTable::class)->get($assid),
        'vehicleObj'     => $this->getDefinedTable(Assessment\HiringvehicleTable::class),
    ]);

    $ViewModel->setTerminal(true);
    return $ViewModel;
}


	 /**
	  * Step 4 -- OVERVIEW
	 **/
	public function step4Action()
	{
		$this->init();
		$application_id = $this->_id;
		$array_id = explode("_", $this->_id);
		$maxstep = $this->getDefinedTable(Assessment\AssessmentTable::class)->getColumn($application_id,'maxstep');
		//echo'<pre>';print_r($array_id);exit;
		if($maxstep < 4):
			$this->getDefinedTable(Assessment\AssessmentTable::class)->save(array('id'=>$array_id[0],'maxstep'=>'4'));
		endif;
		$applications = $this->getDefinedTable(Assessment\AssessmentTable::class)->get($application_id);
		/**--Disapearing the notification--*/
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1','priority'=>'0');//priority red dot(==0....remove)
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		return new ViewModel([
			'title'               => 'Step 4- Overview',
			'login_id'            =>$this->_login_id,
			'application'         => $applications,
			'login_role'          =>$this->_login_role,
			'attendance'          =>$this->getDefinedTable(Assessment\AttendanceTable::class),
			'category'            => $this->getDefinedTable(Assessment\WorkcategoryTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class),
			'infa' => $this->getDefinedTable(Assessment\InfrastructureTable::class)->getByAssessmentId($this->_id),
			'explosive' => $this->getDefinedTable(Assessment\ExplosiveTable::class)->getByAssessmentId($this->_id),
			'companys' => $this->getDefinedTable(Assessment\TruckassignmentTable::class)->getByAssessmentId($this->_id),
			'attendence'          =>$this->getDefinedTable(Assessment\AttendanceTable::class)->getByAssessmentId($this->_id),
			'extractiondetails'   =>$this->getDefinedTable(Assessment\ExtractionTable::class)->getByAssessmentId($this->_id),
			'company'        =>$this->getDefinedTable(Assessment\HiringcompanyTable::class),
			'attendancObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'asset' => $this->getDefinedTable(Asset\AssetmanagementTable::class),
		    'masterexplosive' => $this->getDefinedTable(Assessment\MasterexplosiveTable::class),
			'vechObj' => $this->getDefinedTable(Assessment\HiringvehicleTable::class),
			'dispatchtypeObj' => $this->getDefinedTable(Assessment\DispatchedTable::class),
			'statusObj' => $this->getDefinedTable(Assessment\StatusTable::class),
			'emp' => $this->getDefinedTable(Hr\EmployeeTable::class)
		]);
	}
	/**
	 * FLOW Function -- Initiation
	 */
	public function flowinitiation($process_id, $application_id)
	{   
		$flow_id = $this->getDefinedTable(Administration\FlowTable::class)->getColumn(array('process'=>$process_id),'id');
		//echo $flow_id; exit;
		$flow_role = $this->getDefinedTable(Administration\FlowTable::class)->getColumn($flow_id,'role');
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get(array('flow'=>$flow_id,'action_performed'=>'0'));
		//echo '<pre>'; print_r($privileges);exit;
		if($flow_id):
			foreach($privileges as $privilege);
			$data = array(
				'flow'             => $flow_id,
				'application'      => $application_id,
				'activity'         => 1,
				'actor'            => $privilege['route_to_role'],
				'status'           => $privilege['status_changed_to'],
				'action'           => $privilege['action'],
				'routing'          => $flow_role,
				'routing_status'   => $privilege['status_changed_to'],
				'action_performed' => $privilege['action_performed'],
				'description'      => "Assesment Application Initiated",
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($data);
			return $flow_result;
		else:
			return '0';
		endif;
	}


	/**
	 * Credit application Flow Action
	 * processAction
	 * @app_actions, @app_privileges, @app_transaction_flow @sys_status, @sys_roles
	 */
		public function processAction()
{
    $this->init();

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();
        $process_id = '1309';
        $flow_id = $form['flow'];
        $action_id = $form['action'];
        $application_id = $form['application'];
        $remark = $form['remarks'];
        $application_focal = $this->_author;
        $current_flow = $this->getDefinedTable(Administration\FlowTransactionTable::class)->get($flow_id);

        if (empty($current_flow)) {
            $this->flashMessenger()->addMessage("error^ Flow not found.");
            return $this->redirect()->toRoute('q', array('action' => 'step4', 'id' => $application_id));
        }

        $flow = $current_flow[0];
        $privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get([
            'flow' => $flow['flow'],
            'action_performed' => $action_id
        ]);

        foreach ($privileges as $privilege) {
            if ($privilege['status_changed_to'] == 4) {
                $extraction = $this->getDefinedTable(Assessment\ExtractionTable::class)
                    ->getByAssessmentId(['assessment_id' => $application_id]);
					//echo '<pre>';print_r($extraction);exit;

                foreach ($extraction as $extractionRow) {
                 if (is_numeric($extractionRow['weight'])) {
                    switch ($extractionRow['type_ofdispatche']) {
                                case 1:
									$opening= $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>1,'item'=>7));
									foreach($opening as $openings);
                                    
                                        $updated_quantity = $openings['quantity'] + $extractionRow['weight'];
                                        $stock_data = [
                                            'id' => $openings['id'],
                                            'quantity' => $updated_quantity,
                                            'modified' => $this->_modified,
                                            'author' => $this->_author,
                                        ];
                                        $stock_data = $this->_safedataObj->rteSafe($stock_data);
                                        $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($stock_data);
                                    
                                    break;

                                case 2:
                                    $opening= $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>1,'item'=>6));
									foreach($opening as $openings);
                                        $updated_quantity = $openings['quantity'] + $extractionRow['weight'];
                                        $stock_data = [
                                            'id' => $openings['id'],
                                            'quantity' => $updated_quantity,
                                            'modified' => $this->_modified,
                                            'author' => $this->_author,
                                        ];
                                        $stock_data = $this->_safedataObj->rteSafe($stock_data);
                                        $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($stock_data);
                                    
                                    break;
                            }
                        }

                }
            }

            $app_data = [
                'id' => $application_id,
                'status' => $privilege['status_changed_to'],
                'modified' => $this->_modified
            ];
            $app_data = $this->_safedataObj->rteSafe($app_data);
            $this->_connection->beginTransaction();
            $app_result = $this->getDefinedTable(Assessment\AssessmentTable::class)->save($app_data);

            if ($app_result) {
                $activity_data = [
                    'process' => $process_id,
                    'process_id' => $application_id,
                    'status' => $privilege['status_changed_to'],
                    'remarks' => $remark,
                    'role' => $flow['actor'],
                    'author' => $this->_author,
                    'created' => $this->_created,
                    'modified' => $this->_modified,
                ];
                $activity_data = $this->_safedataObj->rteSafe($activity_data);
                $activity_result = $this->getDefinedTable(Acl\ActivityLogTable::class)->save($activity_data);

                if ($activity_result) {
                    if ($privilege['route_to_role']) {
                        $next_activity_no = $flow['activity'] + 1;
                        $flow_data = [
                            'flow' => $flow['flow'],
                            'role_id' => $application_focal,
                            'application' => $application_id,
                            'activity' => $next_activity_no,
                            'actor' => $privilege['route_to_role'],
                            'status' => $privilege['status_changed_to'],
                            'action' => $privilege['action'],
                            'routing' => $flow['actor'],
                            'routing_status' => $flow['status'],
                            'description' => $remark,
                            'author' => $this->_author,
                            'created' => $this->_created,
                            'modified' => $this->_modified
                        ];

                        $flow_result = $this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow_data);

                        if ($flow_result) {
                            $this->notify($application_id, $privilege['id'], $remark, $flow_data['role_id']);
                            $this->getDefinedTable(Administration\FlowTransactionTable::class)->performed($flow_id);
                            $this->_connection->commit();
                            $this->flashMessenger()->addMessage("success^ Successfully applied <strong>" . $action_id . "</strong>!");
                        } else {
                            $this->_connection->rollback();
                            $this->flashMessenger()->addMessage("error^ Failed to update application apply <strong>" . $action_id . "</strong> action.");
                        }
                    } else {
                        $this->getDefinedTable(Administration\FlowTransactionTable::class)->remove($application_id);
                        $this->_connection->commit();
                        $this->flashMessenger()->addMessage("success^ Successfully removed and approved or rejected or aborted the Application.");
                    }
                } else {
                    $this->_connection->rollback();
                    $this->flashMessenger()->addMessage("error^ Failed to register the forward action in activity log.");
                }
            } else {
                $this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to update Application status for forward action.");
            }

            return $this->redirect()->toRoute('q', array('action' => 'step4', 'id' => $application_id));
        }
    }

    $login = [
        'login_id' => $this->_login_id,
        'login_role' => $this->_login_role,
    ];

    $viewModel = new ViewModel([
        'title' => 'Assessment Application',
        'flow_id' => $this->_id,
        'login' => $login,
        'role' => $this->_login_role,
        'assesObj' => $this->getDefinedTable(Assessment\AssessmentTable::class),
        'flowprivilegeObj' => $this->getDefinedTable(Administration\FlowPrivilegeTable::class),
        'flowtransactionObj' => $this->getDefinedTable(Administration\FlowTransactionTable::class),
        'flowactionObj' => $this->getDefinedTable(Administration\FlowActionTable::class),
        'roleObj' => $this->getDefinedTable(Acl\RolesTable::class),
    ]);
    $viewModel->setTerminal(true);
    return $viewModel;
}





	/**--NOTIFY FUNCTION-/
	/**
	 * Notification Action
	 */
	public function notify($application_id,$privilege_id,$role_id,$remarks = NULL,)
	{
		$userlists='';
		$applications = $this->getDefinedTable(Assessment\AssessmentTable::class)->get($application_id);
		foreach($applications as $app);
		$privileges = $this->getDefinedTable(Administration\FlowPrivilegeTable::class)->get($privilege_id);
		foreach ($privileges as $flow) {
			$notify_msg = $app['application_no']." - ".$flow['description']."<br>[".$remarks."]";
			$notification_data = array(
				'route'         => 'q',
				'action'        => 'step4',
				'key' 		    => $application_id,
				'description'   => $notify_msg,
				'author'	    => $this->_author,
				'created'       => $this->_created,
				'modified'      => $this->_modified,   
			);
			//echo '<>pre';print_r($privileges);exit;
			$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
			if($notificationResult > 0 ){
				$notification_array = explode("|", $flow['route_notification_to']);
				//echo '<pre>';print_r($notification_array);exit;
				if(sizeof($notification_array)>0){
					for($k=0;$k<sizeof($notification_array);$k++){
						$focalusers=$this->getDefinedTable(Administration\FlowTransactionTable::class)->get(array('role_id'=>$role_id,'application'=>$application_id));
						foreach($focalusers as $applicationfocal):
						$focal_id = $applicationfocal['role_id'];
						if($notification_array[$k]=='2'){
							//Role 2- User Level
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$focal_id,'status'=>'1'));
							echo 'Condition1';
						}
						elseif($notification_array[$k]=='3'){
							//Role 3- Regional Level
							$region=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($focal_id,'region');
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'region'=>$region,'status'=>'1'));
							echo 'Condition2';
						}
						else{
							//Role 3- Financial Division level
							$userlists = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>$notification_array[$k],'status'=>'1'));
							echo 'Condition3';
						}
						endforeach;
						//echo '<pre>';print_r($userlists);exit;
						$email_array = []; 
						$loop_count = 1;
						foreach($userlists as $userlist):
							$notify_data = array(
								'notification' => $notificationResult,
								'user'    	   => $userlist['id'],
								'flag'    	   => '0',
								'desc'    	   => $notify_msg,
								'author'	   => $this->_author,
								'created'      => $this->_created,
								'modified'     => $this->_modified,  
							);
							if($flow['notification'] == 1){
								$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
							}
							if($loop_count == 1){
								$recipient_email = $userlist['email'];
								$recipient_name = $userlist['name'];
							}else{
								array_push($email_array, ['email'=>$userlist['email'],'name'=>$userlist['name']]);
							}
							$loop_count += 1;
						endforeach;
					}
				}
				
			}               	
		}
	}
}