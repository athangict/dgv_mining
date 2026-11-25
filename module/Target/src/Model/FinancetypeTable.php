<?php
namespace Target\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class FinancetypeTable extends AbstractTableGateway
{
	protected $table = 'fa_target_type'; //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table);
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Return report records of table
	 * @return Array
	 */
	public function getPWorklist($data)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
				->order('id desc');
		if($data['district'] != '-1'){
			$select->where(array('district'=>$data['district']));
		}  	
	    if($data['block'] != '-1'){
			$select->where(array('block'=>$data['block']));
		} 
		if($data['category'] != '-1'){
			$select->where(array('category'=>$data['category']));
		}  	
		if($data['identity'] != '-1'){
			$select->where(array('identity'=>$data['identity']));
		} 
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	/**
	 * Return report records of table
	 * @return Array
	 */
	public function getPWorkReport($data)
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
				->order('id desc');
		if($data['district'] != '-1'){
			$select->where(array('district'=>$data['district']));
		}  	
	    if($data['block'] != '-1'){
			$select->where(array('block'=>$data['block']));
		} 
		if($data['category'] != '-1'){
			$select->where(array('category'=>$data['category']));
		}  	
		if($data['identity'] != '-1'){
			$select->where(array('identity'=>$data['identity']));
		} 
		if($data['ownership'] != '-1'){
			$select->where(array('own_ownership_type'=>$data['ownership']));
		} 
		if($data['work_category'] != '-1'){
			$select->where(array('work_category'=>$data['work_category']));
		} 
		if($data['work_subcategory'] != '-1'){
			$select->where(array('work_subcategory'=>$data['work_subcategory']));
		} 
		if($data['status'] != '-1'){
			$select->where(array('status'=>$data['status']));
		} 
		if($data['fiscal_year'] != '-1'){
			$select->where(array('fiscal_year'=>$data['fiscal_year']));
		} 
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		        ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
     * Return column value of given where condition | id
     * @param Int|array $parma
     * @param String $column
     * @return String | Int
     */
    public function getColumn($param, $column)
    {         
		$where = ( is_array($param) )? $param: array('id' => $param);
		$fetch = array($column);
		$adapter = $this->adapter;       
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns($fetch);
		$select->where($where);

		$selectString = $sql->getSqlStringForSqlObject($select); 
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();          
		$columns = "";
		foreach ($results as $result):
			$columns =  $result[$column];
		endforeach; 
		
		return $columns;       
    }
/**
 * Return sum of production quantity from production table based on item and date range.
 * @param Array $param - Conditions to filter data (e.g., 'item').
 * @param Array $data - Date range with 'start_date' and 'end_date'.
 * @return String | Int - Sum of the production quantity.
 */
public function getSumCurrent($param, $data)
{

    $where = (is_array($param)) ? $param : array('item_group' => $param);

    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = $sql->select();

    $select->from('crusher_product')
           ->columns(array('product_qty')) 
           ->where($where) 
           ->where->between('crusher_product.date', $data['start_date'], $data['end_date']); // Filter by date range

    $selectString = $sql->getSqlStringForSqlObject($select);

    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

    if (empty($results)) {
        return 0;
    }
    $totalQuantity = 0;
    foreach ($results as $row) {
        $totalQuantity += (float) $row['product_qty']; 
    }

    return $totalQuantity;
}
/**
 * Return product and its quantity from the production table based on item and date range.
 * @param Array $param - Conditions to filter data (e.g., 'item').
 * @param Array $data - Date range with 'start_date' and 'end_date'.
 * @return Array - List of products and their quantities.
 */
public function getProductDetails($param, $data)
{
    $where = (is_array($param)) ? $param : array('item_group' => $param);

    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = $sql->select();

    // Select columns: 'product' and 'product_qty'
    $select->from('crusher_product')
           ->columns(array('product', 'product_qty')) // Fetch both product name and quantity
           ->where($where)
           ->where->between('crusher_product.date', $data['start_date'], $data['end_date']); // Filter by date range

    $selectString = $sql->getSqlStringForSqlObject($select);

    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

    if (empty($results)) {
        return [];  // Return an empty array if no products are found
    }

    return $results;  // Return all products with their quantities
}



public function getSumQuaterly($data, $column, $where = null)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();

		switch ($data) {
			case 1:
				$start_date = date('Y').'-01-01';
				$end_date   = date('Y').'-03-31';
				break;
			case 2:
				$start_date = date('Y').'-04-01';
				$end_date   = date('Y').'-06-30';
				break;
			case 3:
				$start_date = date('Y').'-07-01';
				$end_date   = date('Y').'-09-30';
				break;
			case 4:
				$start_date = date('Y').'-10-01';
				$end_date   = date('Y').'-12-31';
				break;
			default:
				return 0; 
		}

		$select->from(array('bd'=>$this->table))
		->join(array('b'=>'crusher_productiondtls'),'b.id = bd.crusher_no')
			   ->columns([
					
				   'sum' => new Expression('SUM(' . $column . ')')
			   ]);

		$select->where->between('b.date', $start_date, $end_date);

		if ($where !== null) {
			$select->where($where);
		}

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		$sum = isset($results[0]['sum']) && $results[0]['sum'] !== null ? $results[0]['sum'] : 0;

		return $sum;
	}
/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getActualTotalByMonth($year, $month, $column)
{
    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = $sql->select();

    $select->from(['bd' => $this->table])
        ->join(['b' => 'crusher_productiondtls'], 'b.id = bd.crusher_no')
        ->columns([
            'sum' => new Expression('SUM(' . $column . ')')
        ]);

    $select->where->equalTo(new Expression('YEAR(b.date)'), $year);
    $select->where->equalTo(new Expression('MONTH(b.date)'), $month);

    $selectString = $sql->getSqlStringForSqlObject($select);
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

    $sum = isset($results[0]['sum']) && $results[0]['sum'] !== null ? $results[0]['sum'] : 0;

    return $sum;
}

	/*
	 * Save record
	 * @param String $array
	 * @return Int
	 */
	public function save($data)
	{
	    if ( !is_array($data) ) $data = $data->toArray();
	    $id = isset($data['id']) ? (int)$data['id'] : 0;
	    
	    if ( $id > 0 )
	    {
	    	$result = ($this->update($data, array('id'=>$id)))?$id:0;
	    } else {
	        $this->insert($data);
	    	$result = $this->getLastInsertValue(); 
	    }	    	    
	    return $result;	     
	}

	/**
     *  Delete a record
     *  @param int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete(array('id' => $id));
	}

	/**
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['min'];
		endforeach;
	
		return $column;
	}
	
	/**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMax($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return $column;
	}	
	
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMaxRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MAX('.$column.')')
		));
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->where($where)
				->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMinRow($column,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
	
		$sub0 = new Select($this->table);
		$sub0->columns(array(
				$column => new Expression('MIN('.$column.')')
		));
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		->where($where)
		->where($column);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getApplicationNo($prefix_code,$column)
	{  
		$adapter = $this->adapter;			
		$sql = new Sql($adapter);		
		$select = $sql->select();
		$select->from($this->table);		
		$select->where->like($column, $prefix_code."%");	
			
	    $selectString = $sql->getSqlStringForSqlObject($select);
		//echo  $selectString ;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return  $results;
	}
	/**
	 * Return Distinct value of the column
	 * @param Array $where
	 * @param String $column
	 * @return Array | Int
	 */
	public function getDistinct($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'distinct' => new Expression('DISTINCT('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		$column = array();
		foreach ($results as $result):
			array_push($column,$result['distinct']);
		endforeach;
	
		return $column;
	}
	/**
	 * Return sum value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Decimal
	 */
	public function getSum($column,$where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'sum' => new Expression('SUM('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;

		return number_format((float)$column, 3, '.', '');
	}
	/**
	 * Return Count value of the column
	 * @param Array $where
	 * @return String | Int
	 */
	public function getCount($where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			->columns(array('count' => new Expression('COUNT(*)')));
		if($where != NULL):
			$select->where($where);
		endif;
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		foreach($results as $row);		
		return $row['count'];
	}
}	