<?php
namespace Assessment\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Interop\Container\ContainerInterface;
use Assessment\Model as Assessment;
use Acl\Model as Acl;
use Administration\Model as Administration;
use Hr\Model as Hr;
use Accounts\Model as Accounts;

class MachinebdController extends AbstractActionController
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
    protected $_connection;

    public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }

    public function getDefaultTable($table)
    {
        $this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
        return $this->_table;
    }

    public function getDefinedTable($table)
    {
        return $this->_container->get($table);
    }

    public function init()
    {
        $this->_auth = new AuthenticationService;
        if (!$this->_auth->hasIdentity()) {
            $this->flashMessenger()->addMessage('error^ You don\'t have permission to access this page!');
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        if (!isset($this->_config)) {
            $this->_config = $this->_container->get('Config');
        }
        if (!isset($this->_user)) {
            $this->_user = $this->identity();
        }
        if (!isset($this->_login_id)) {
            $this->_login_id = $this->_user->id;
        }
        if (!isset($this->_login_role)) {
            $this->_login_role = $this->_user->role;
        }
        if (!isset($this->_author)) {
            $this->_author = $this->_user->id;
        }

        $this->_id = $this->params()->fromRoute('id');
        $this->_created = date('Y-m-d H:i:s');
        $this->_modified = date('Y-m-d H:i:s');

        $fileManagerDir = $this->_config['file_manager']['dir'];

        if (!is_dir($fileManagerDir)) {
            mkdir($fileManagerDir, 0777);
        }

        $this->_dir = realpath($fileManagerDir);
        $this->_safedataObj = $this->safedata();
        $this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
    }

    public function machinebdAction()
    {
        $this->init();
        $page = (int) $this->params()->fromRoute('page', 1);
        $itemsPerPage = 20;
        $machineTable = $this->getDefinedTable(Assessment\MachineTable::class);
        $paginator = new Paginator(new ArrayAdapter($machineTable->getAll()));

        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($itemsPerPage);
        $paginator->setPageRange(8);

        return new ViewModel([
            'title'          => 'Step 1 - Machine Details',
            'page'           => $page,
            'itemsPerPage'   => $itemsPerPage,
            'machine'        => $this->getDefinedTable(Assessment\MachineTable::class)->getAll(),
        ]);
    }

public function addmachineAction()
{
    $this->init();

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();
        $isSuccess = true;
            $machineNoData = [
                'service_code'  => 'A',
                'date'          => $this->_created,
                'column'        => 'machine_no',
                'tableObj'      => $this->getDefinedTable(Assessment\MachineTable::class),
            ];
            $machineNo = $this->generateMachineNo($machineNoData);

            // Insert Machine Data
            $machineData = [
                'machine_no' => $machineNo,
                'status'     => 1,
                'created'    => $this->_created,
                'modified'   => $this->_modified,
            ];
            $this->getDefinedTable(Assessment\MachineTable::class)->save($machineData);

            // Insert Breakdown Data
            $machinebdData = [
                'machine_type'    => $form['machine_type'],
                'cause'           => $form['cause'],
                'duration_of_bd'  => $form['duration_of_bd'],
                'person_involved' => $form['person_involved'],
                'action_taken'    => $form['action_taken'],
                'machine_no'      => $machineNo,  // Correct reference to $machineNo
                'created'         => $this->_created,
                'modified'        => $this->_modified,
            ];

       // echo'<pre>';print_r($machinebdData);exit;
            $machinebdData = $this->_safedataObj->rteSafe($machinebdData);
			$machinedetailsInsertResult = $this->getDefinedTable(Assessment\MachinedetailsTable::class)->save($machinebdData);
	
			if ($isSuccess) {
				$this->flashMessenger()->addMessage("success^ Products successfully updated in stock.");
			}
	
			return $this->redirect()->toRoute('machinebd', ['action' => 'machinebd', 'page' => $this->_id]);
		}

    // Return the view model for rendering
    $viewModel = new ViewModel([
        'title' => 'Add Machine Breakdown',
    ]);

    // Set to render in terminal
    $viewModel->setTerminal(true);
    return $viewModel;
}


public function generateMachineNo($data)
{
    $service_code = $data['service_code'];
    $year = date('y', strtotime($data['date']));  
    $month = date('m', strtotime($data['date']));
    $prefix_no = $service_code . $year . $month;
    $column = $data['column'];
    $tableObj = $data['tableObj'];

    // Start a database transaction to prevent race conditions
    $tableObj->getAdapter()->getDriver()->getConnection()->beginTransaction();

    try {
        // Fetch existing machine numbers from the database
        $results = $tableObj->select(["machine_no LIKE ?" => $prefix_no . "%"]);

        // Extract serial numbers from the fetched results
        $array_list = [];
        foreach ($results as $result) {
            $serial = substr($result[$column], 7); // Assuming serial number starts from 8th character
            array_push($array_list, (int)$serial); // Store as integer for comparison
        }

        // Calculate the next serial number
        $next_serial = (count($array_list) > 0) ? max($array_list) + 1 : 1;

        // Format the serial number to have leading zeros (5 digits)
        $next_tr_serial = str_pad($next_serial, 5, '0', STR_PAD_LEFT);

        // Generate the machine number
        $machine_no = $prefix_no . $next_tr_serial;

        // Check if the generated machine number already exists (double-check for concurrency)
        $existing = $tableObj->select(["machine_no" => $machine_no])->current();
        if ($existing) {
            throw new \Exception("Duplicate machine number detected: " . $machine_no);
        }

        // Commit the transaction
        $tableObj->getAdapter()->getDriver()->getConnection()->commit();

        // Return the generated machine number
        return $machine_no;
    } catch (\Exception $e) {
        // Rollback the transaction in case of an error
        $tableObj->getAdapter()->getDriver()->getConnection()->rollback();
        error_log("Error generating machine number: " . $e->getMessage());
        throw $e; // Re-throw the exception for further handling
    }
}
  public function viewMachineDetailsAction()
    {
		$this->init();
		$machinedtls = $this->getDefinedTable(Assessment\MachinedetailsTable::class)->getAll($this->_id);
		$machineObj = $this->getDefinedTable(Assessment\MachineTable::class)->get($this->_id);
		return new ViewModel(array(
			'machinedtls' => $machinedtls,
			'machineObj'  => $machineObj,
			
			
			
		) );
	}

}