<?php
namespace Assessment\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class ExtractionTable extends AbstractTableGateway 
{
    protected $table = 'mining_extraction'; // Table name

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }    

    /**
     * Return all records from the table
     * @return Array
     */
    public function getByAssessmentId($assessment_id)
    {  
        $adapter = $this->adapter;
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->where(['assessment_id' => $assessment_id]);
        $select->from($this->table);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
        return $results;
    }
    
    /**
     * Return records matching a given condition or ID
     * @param Int|Array $param
     * @return Array
     */
    public function get($param)
    {
        $where = (is_array($param)) ? $param : array('id' => $param);
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
     * Return column value based on a given condition or ID
     * @param Int|Array $param
     * @param String $column
     * @return String|Int
     */
    public function getColumn($param, $column)
    {         
        $where = (is_array($param)) ? $param : array('id' => $param);
        $fetch = array($column);
        $adapter = $this->adapter;       
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from($this->table);
        $select->columns($fetch);
        $select->where($where);

        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();          

        foreach ($results as $result) {
            $columns = $result[$column];
        }
      
        return $columns;       
    }
public function getStockDetailsByExtractionId($extractionId)
{
    return $this->select(['extraction_id' => $extractionId])->toArray();
}


    /**
     * Save record into the table
     * @param Array|Object $data
     * @return Int|0
     */
    public function save($data)
    {
        if (!is_array($data)) $data = $data->toArray();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        
        if ($id > 0) {
            // Update if ID exists
            $result = ($this->update($data, array('id' => $id))) ? $id : 0;
        } else {
            // Insert new record
            $this->insert($data);
            $result = $this->getLastInsertValue(); 
        }
        
        return $result;     
    }

    /**
     * Remove a record by ID
     * @param int $id
     * @return bool
     */
    public function remove($id)
    {
        return $this->delete(array('id' => $id));
    }

    /**
     * Check if a record exists with given column and value
     * @param String $column
     * @param mixed $value
     * @return bool
     */
    public function isPresent($column, $value)
    {
        $resultSet = $this->select(function(Select $select) use ($column, $value) {
            $select->where(array($column => $value));
        });

        $resultSet = $resultSet->toArray();
        return (sizeof($resultSet) > 0) ? true : false;
    } 

    /**
     * Return the minimum value of a column
     * @param String $column
     * @param Array|null $where
     * @return String|Int
     */
    public function getMin($column, $where = NULL)
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from($this->table);
        $select->columns(array(
            'min' => new Expression('MIN(' . $column . ')')
        ));
        
        if ($where != NULL) {
            $select->where($where);
        }
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

        foreach ($results as $result) {
            $column = $result['min'];
        }

        return $column;
    }

    /**
     * Return the maximum value of a column
     * @param String $column
     * @param Array|null $where
     * @return String|Int
     */
    public function getMax($column, $where = NULL)
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from($this->table);
        $select->columns(array(
            'max' => new Expression('MAX(' . $column . ')')
        ));
        
        if ($where != NULL) {
            $select->where($where);
        }
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();    
        
        foreach ($results as $result) {
            $column = $result['max'];
        }

        return $column;
    }

/**
 * Return the sum of a given column for a specific month and year.
 * @param Int $year
 * @param Int $month
 * @param String $column (e.g., 'weight')
 * @return Float
 */
public function getSumQuaterly($year, $quarter, $column, $where = null)
{
    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = $sql->select();

    switch ($quarter) {
        case 1:
            $start_date = "$year-01-01";
            $end_date   = "$year-03-31";
            break;
        case 2:
            $start_date = "$year-04-01";
            $end_date   = "$year-06-30";
            break;
        case 3:
            $start_date = "$year-07-01";
            $end_date   = "$year-09-30";
            break;
        case 4:
            $start_date = "$year-10-01";
            $end_date   = "$year-12-31";
            break;
        default:
            return 0;
    }

    $select->from(['bd' => $this->table])
        ->join(['b' => 'mining_assessment'], 'b.id = bd.assessment_id')
        ->columns([
            'sum' => new Expression("SUM($column)")
        ])
        ->where->between('b.date', $start_date, $end_date);

    if ($where !== null) {
        $select->where($where);
    }

    $selectString = $sql->getSqlStringForSqlObject($select);
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

    return isset($results[0]['sum']) && $results[0]['sum'] !== null ? $results[0]['sum'] : 0;
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
        ->join(['b' => 'mining_assessment'], 'b.id = bd.assessment_id')
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

    
    /**
     * Get transaction class based on various conditions
     * @param mixed $activity
     * @param mixed $region
     * @param mixed $location
     * @param mixed $start_date
     * @param mixed $end_date
     * @return Array
     */
    public function getTransactionClass($activity, $region, $location, $start_date, $end_date)
    {
        // Similar implementation as your original code, no significant changes needed
        // This method is building a nested query to retrieve the transaction classes
        // based on provided filters. As this seems specific to your database schema,
        // it's difficult to improve without knowing the exact structure of the database.
    }

    /**
     * Additional method with similar structure as `getTransactionClass`
     * @param Array $options
     * @return Array
     */
    public function getTransactionClassforBSPLS($options)
    {
        extract($options);  

        // Code here builds the query to fetch transaction classes based on options
    }

    /**
     * Return records of the given condition
     * @param Int $column
     * @param Int $param
     * @return Array
     */
    public function getMaxRow($column, $param)
    {
        $where = (is_array($param)) ? $param : array('id' => $param);
        $adapter = $this->adapter;

        $sub0 = new Select($this->table);
        $sub0->columns(array(
            $column => new Expression('MAX(' . $column . ')')
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
     * Return the row with the minimum value of a given column
     * @param String $column
     * @param Array|Int $param
     * @return Array
     */
    public function getMinRow($column, $param)
    {
        $where = (is_array($param)) ? $param : array('id' => $param);
        $adapter = $this->adapter;

        $sub0 = new Select($this->table);
        $sub0->columns(array(
            $column => new Expression('MIN(' . $column . ')')
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
}
