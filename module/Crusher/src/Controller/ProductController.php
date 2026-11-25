<?php
namespace Crusher\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Crusher\Model As Crusher; 
use Acl\Model As Acl;
use Hr\Model As Hr;
use Stock\Model As Stock;
use Fleet\Model As Fleet;
use Administration\Model As Administration;
class ProductController extends AbstractActionController
{
    private $_container;

protected $_table;          // Database table
protected $_user;           // User details

protected $_login_id;       // Logged-in user ID
protected $_login_role;     // Logged-in user role

protected $_author;
protected $_created;
protected $_modified;
protected $_config;
protected $_dir;
protected $_id;
protected $_auth;

protected $_userloc;
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
	// public function indexAction()
	// {
	// 	$this->init();		
			
	// 	return new ViewModel( array(
	// 			'title' => "View  Crusher",
	// 	));   
	// }

	/**
	 * Create CrusherActions
	 **/
	public function crushedproAction()
	{
		$this->init();
		$page = (int) $this->params()->fromRoute('page', 1); 
		$itemsPerPage = 20;
		$productTable = $this->getDefinedTable(Crusher\ProductTable::class);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($productTable->getAll()));
	

		$paginator->setCurrentPageNumber($page);
		$paginator->setItemCountPerPage($itemsPerPage);
		$paginator->setPageRange(8);
	
		return new ViewModel([
			'title' => 'Step 1 - Infrastructure Details',
			//'paginator'      => $paginator, 
			'page'           => $page,        
			'itemsPerPage'   => $itemsPerPage,
			'product'     =>$this->getDefinedTable(Crusher\ProductTable::class)->getAll(),
           //'itemObj'   => $this->getDefinedTable(Stock\ItemTable::class),
			//'productObj'      => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),

		]);
	}
public function addstoneproductAction()
{
    $this->init();

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();
        $isSuccess = true;
        $location_code = $this->getDefinedTable(Administration\LocationTable::class)
            ->getColumn($this->_login_location, 'location_code');
			
        $crusherNoData = [
            'service_code'  => 'A',
            'location_code' => $location_code,
            'date'          => $this->_created,
            'column'        => 'crusher_no',
            'tableObj'      => $this->getDefinedTable(Crusher\ProductTable::class),
        ];
        $crusherNo = $this->generateCrusherNo($crusherNoData);

        $productData = [
            'crusher_no' => $crusherNo,
            'status'     => 1,
            'created'    => $this->_created,
            'modified'   => $this->_modified,
        ];
        $productInsertResult = $this->getDefinedTable(Crusher\ProductTable::class)->save($productData);

        $productQtyList = [];

        if (!empty($form['product_id']) && is_array($form['product_id'])) {
            foreach ($form['product_id'] as $index => $productId) {
                $productQty = isset($form['product_qty'][$index]) ? (float) $form['product_qty'][$index] : 0;
                $productQty = round($productQty, 2);


                $stockItem7 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(['item' => 7]);
                if (!empty($stockItem7)) {
                    foreach ($stockItem7 as $stockDetail) {
                        if ($stockDetail['quantity'] <= 0) {
                            continue;
                        }

                        $newQuantity = max(0, $stockDetail['quantity'] - $productQty);

                        $updatedStockData = [
                            'id'       => $stockDetail['id'],
                            'quantity' => $newQuantity,
                            'modified' => $this->_modified,
                        ];
                        $saveResult = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($updatedStockData);
                    }
                }

                $productStockDetails = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get([
                    'item' => $productId,
                    'location' => 2
                ]);

                if (!empty($productStockDetails)) {
                    foreach ($productStockDetails as $stockDetails) {
                        $currentQuantity = is_null($stockDetails['products']) ? 0 : $stockDetails['products'];
                        $currentQuantity = is_null($stockDetails['quantity']) ? 0 : $stockDetails['quantity'];

                        $newQuantity = max(0, $currentQuantity + $productQty);

                        $updatedStockDatas = [
                            'id'       => $stockDetails['id'],
                            'products' => $newQuantity,
                            'quantity' => $newQuantity,
                            'modified' => $this->_modified,
                        ];

                        $saveResults = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($updatedStockDatas);
                    }
                }

                $productionData = [
                    'stock_qty'    => $form['stock_qty'],
                    'date'         => $form['date'],
                    'output_type'  => $form['output_type'],
                    'quantity'     => $form['quantity'],
                    'product_qty'  => $productQty,
                    'product'      => $productId,
                    'item_group'   => 3,
                    'crusher_no'   => $crusherNo,
                    'status'       => 1,
                    'created'      => $this->_created,
                    'modified'     => $this->_modified,
                ];

                $productionData = $this->_safedataObj->rteSafe($productionData);
                $productionInsertResult = $this->getDefinedTable(Crusher\ProductionTable::class)->save($productionData);
            }
        }

        if ($isSuccess) {
            $this->flashMessenger()->addMessage("success^ Products successfully updated in stock.");
        }

        return $this->redirect()->toRoute('product', ['action' => 'crushedpro', 'page' => $this->_id]);
    }

    $itemgroups = $this->getDefinedTable(Stock\ItemTable::class)->getAll();


    $viewModel = new ViewModel([
        'title'       => 'Add Company',
        'group'       => $this->getDefinedTable(Stock\OpeningStockTable::class),
        'itemgroups'  => $this->getDefinedTable(Stock\ItemGroupTable::class)->getAll(),
        'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
        'productObj'  => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
        'stockObj'    => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getAll(),
        'itemgroups'  => $itemgroups,
    ]);


    $viewModel->setTerminal(true);
    return $viewModel;
}




	
	

	

	/**** generate unigue Id ***/
	public function generateCrusherNo($data)
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
	
		return $crusher_no = $prefix_no . $next_tr_serial;
	}
	/**
	 * viewproductAction 
	 * **/
	public function viewproductAction(){
		$this->init();
		return new ViewModel(array(
			'id' => $this->_id,
			'productObj' => $this->getDefinedTable(Crusher\ProductTable::class)->get($this->_id),
			'productDetails'  => $this->getDefinedTable(Crusher\ProductionTable::class)->getAll($this->_id),
			'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
			'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
            // 'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
		));
	}
	/**
	 * getitem - Get item based on location
	 * **/
public function getItemChangeAction()
	{
		//echo("Hello");
		$form = $this->getRequest()->getPost();
		$locationId = $form['locationId'];
		$subgroup = $form['item_subgroup'];

		$itemOptions = "<option value='-1'>All</option>";
		if($locationId==-1){
			$itemlist = $this->getDefinedTable(Stock\ItemTable::class)->get(array('item_group'=>$subgroup));
		}
		else{
		$itemlist = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getitems(array('osd.location'=>$locationId,'i.item_group'=>$subgroup));
		}
		//echo($itemlist);
		foreach($itemlist as $item):
			$itemOptions .= "<option value='".$item['id']."'>".$item['name']."</option>";
		endforeach;

		echo json_encode(array(
			'items' => $itemOptions,
		));

		exit;
	}


		/**
	 * getitem - Get item based on location
	 * **/
	public function getuomAction()
	{
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		$selectedUomId = $item[0]['uom'];
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id' => $selectedUomId));
		foreach($item as $items);
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		foreach($uom as $uoms):
			$buom=$uoms['code'];
		endforeach;
		echo json_encode(array(
				'uom' => $buom,
		));
		exit;
	}

	public function getproductuomAction()
	{
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		$selectedUomId = $item[0]['uom'];
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id' => $selectedUomId));
		foreach($item as $items);
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		foreach($uom as $uoms):
			$buom=$uoms['code'];
		endforeach;
		echo json_encode(array(
				'uom' => $buom,
		));
		exit;
	}
	public function getstockAction()
	{
	
		$form = $this->getRequest()->getPost();
		$item = $form['item'];  
	
		$rate = '0.00';
		$quantity = '0.00';
		$cost_price = '0.00';
		
		$itemdetails = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item' => $item));
		
		if (count($itemdetails) > 0) {
			foreach ($itemdetails as $items) {
				
				$rate = $items['selling_price'];  
				$quantity = $items['quantity'];  
				$cost_price = $items['cost_price'];  
			}
		} 
		echo json_encode(array(
			'rate' => $rate,
			'quantity' => $quantity,
			'cost_price' => $cost_price,
		));
		
		exit;
	}
	

	public function editcrushingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(  
				'boulders' 		  => $form['boulders'],
				// 'status'             => $form['status'],
				'author'             => $this->_author,
				'modified'           => $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction();
            $result = $this->getDefinedTable(Crusher\CrusherTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ successfully edited boulders");
            else:
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to edit boulders");
            endif;
			return $this->redirect()->toRoute('crusher', array('action'=>'crusher','page'=>$this->_id, 'id'=>'0'));
        }		
		$ViewModel = new ViewModel([
				'title'    => 'Edit Boulders',
				'crusherview'=> $this->getDefinedTable(Crusher\CrusherTable::class)->getAll($this->_id),
		]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

}