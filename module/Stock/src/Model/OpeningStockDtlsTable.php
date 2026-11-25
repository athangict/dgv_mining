<?php
namespace Stock\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class OpeningStockDtlsTable extends AbstractTableGateway 
{
	protected $table = 'st_opening_stock_dtls'; //tablename

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
	    $select->from($this->table)
				->order(array('id ASC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
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
		       ->where($where)
		       ->order(array('author ASC'));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	/**
	 * Return items of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getitems($param)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('osd'=>$this->table))
		->join(array('ops'=>'st_opening_stock'), 'ops.id = osd.opening_stock',array('location'=>'location'))
		->join(array('i'=>'st_items'), 'i.id = ops.item')
		->where($param);
		$select->where(array('osd.status'=>2));
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		//echo($results);
		return $results;
	}
	
public function getAllStockDetails()
{
    // This function retrieves all stock details from the OpeningStockDtlsTable.
    return $this->select()->toArray();
}

public function updateQuantity($stockId, $newQuantity)
{
    // This function updates the stock quantity for a specific stock record.
    $data = [
        'quantity' => $newQuantity,
        'modified' => time(), // Or use your own modified timestamp
    ];
    return $this->update($data, ['id' => $stockId]);
}


	/**
	 * Return column value of given id
	 * @param Int $id
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
		//echo($results);exit;
	
		foreach ($results as $result):
			$column =  $result[$column];
		endforeach;
		 
		return $column;
	}
	
	/**
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
	
public function saveStockDataWithWeight($data)
{
    // Ensure that data is in array form (if it’s an object, convert it to array)
    if (!is_array($data)) {
        $data = $data->toArray();
    }

    // Check if 'weight' and 'quantity' are numeric before proceeding
    if (!isset($data['weight']) || !is_numeric($data['weight'])) {
        throw new Exception('Invalid weight value');
    }

    // Check if an ID is present in the data (indicating it might be an existing record)
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    // If the ID is greater than 0 (meaning we are updating an existing stock record)
    if ($id > 0) {
        // Fetch the current stock record from the database based on the provided ID
        $existing_stock = $this->get(array('id' => $id));

        // If the stock record exists, update the quantity by adding the weight
        if ($existing_stock) {
            if (!is_numeric($existing_stock['quantity'])) {
                throw new Exception('Invalid quantity value');
            }

            $updated_quantity = $existing_stock['quantity'] + $data['weight']; // Add weight to current quantity

            // Prepare the stock data with the updated quantity
            $stock_data = array(
                'id' => $id,                  // Maintain the ID for the update
                'quantity' => $updated_quantity, // Updated quantity
                'modified' => $data['modified'], // Using the modified timestamp from the input data
                'author' => $data['author'],   // Author from the input data
            );

            // Attempt to update the stock record in the database
            if ($this->update($stock_data, array('id' => $id))) {
                $result = $id;
            } else {
                error_log("Failed to update stock with ID: $id");
                $result = 0;
            }
        } else {
            // If no stock record exists for the given ID, return 0 (could be handled differently, like an error log)
            $result = 0;
        }
    } else {
        // If no ID exists, we are inserting a new stock record with the weight as the quantity
        $stock_data = array(
            'quantity' => $data['weight'], // For new records, set the quantity as the weight
            'modified' => $data['modified'], 
            'author' => $data['author'],   
        );

        // Attempt to insert the new stock record
        if ($this->insert($stock_data)) {
            // Get the last inserted value (new record ID)
            $result = $this->getLastInsertValue();
        } else {
            error_log("Failed to insert new stock data.");
            $result = 0;
        }
    }

    // Return the result (either the ID of the updated/inserted record, or 0 if the operation failed)
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
	* check particular row is present in the table 
	* with given column and its value
	* 
	*/
	public function isPresent($column, $value, $param)
	{
		$where = (is_array($param)) ? $param : array('id' => $param);
	
		$resultSet = $this->select(function(Select $select) use ($column, $value, $where) {
			$select->where(array($column => $value));
			$select->where($where); // This now properly includes the additional where conditions
		});
	
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet) > 0) ? TRUE : FALSE;
	}
	/**
	 * STOCK MOVEMENT
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getSMSUM($column, $where=NULL)
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
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['sum'];
		endforeach;
	
		return $column;
	}
	/**
	 * get By Activity ID
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function getByActivity($item,$location,$group,$class)
	{
		//$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('op'=>$this->table))
	           ->join(array('i'=>'st_items'), 'i.id = op.item')
			   ->join(array('g' =>'st_item_group'), 'g.id = i.item_group')
			   ->join(array('c' =>'st_item_class'), 'c.id = g.item_class')
			   ->order(array('i.name ASC'));
		if($item != '-1'):	   
		    $select->where(array('op.item' => $item));
		endif;
		if($location != '-1'):	   
		    $select->where(array('op.location' => $location));
		endif;
		if($group != '-1'):	   
		    $select->where(array('i.item_group' => $group));
		endif;	
		if($class != '-1'):	   
		    $select->where(array('g.item_class' => $class));
		endif;			
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString;exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
}
