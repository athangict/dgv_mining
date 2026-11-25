<?php

namespace Acl\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;

class NotifyTable extends AbstractTableGateway
{
    protected $table = 'sys_notify';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Return all records.
     */
    public function getAll()
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from(['n' => $this->table])
            ->join(
                ['f' => 'sys_notification'],
                'f.id = n.notification',
                ['route', 'action', 'key']
            )
            ->order('n.id DESC');

        $selectString = $sql->getSqlStringForSqlObject($select);

        return $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();
    }

    /**
     * Return records by id or where clause.
     */
    public function get($param)
    {
        $where = is_array($param) ? $param : ['n.id' => $param];

        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from(['n' => $this->table])
            ->join(
                ['f' => 'sys_notification'],
                'f.id = n.notification',
                ['route', 'action', 'key']
            )
            ->where($where)
            ->order('n.id DESC');

        $selectString = $sql->getSqlStringForSqlObject($select);

        return $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();
    }

    /**
     * Return a single column value.
     */
    public function getColumn($param, $column)
    {
        $where = is_array($param) ? $param : ['id' => $param];

        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from($this->table)
            ->columns([$column])
            ->where($where);

        $selectString = $sql->getSqlStringForSqlObject($select);

        $results = $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();

        return $results[0][$column] ?? null;
    }

    /**
     * Save record.
     */
    public function save($data)
    {
        if (!is_array($data)) {
            $data = $data->toArray();
        }

        $id = isset($data['id']) ? (int) $data['id'] : 0;

        if ($id > 0) {
            return $this->update($data, ['id' => $id]) ? $id : 0;
        }

        $this->insert($data);

        return $this->getLastInsertValue();
    }

    /**
     * Delete record.
     */
    public function remove($id)
    {
        return $this->delete(['id' => $id]);
    }

    /**
     * Check whether a row exists.
     */
    public function isPresent($column, $value)
    {
        $resultSet = $this->select(function (Select $select) use ($column, $value) {
            $select->where([$column => $value]);
        });

        return count($resultSet->toArray()) > 0;
    }

    /**
     * Return minimum value of a column.
     */
    public function getMin($column, $where = null)
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from($this->table)
            ->columns([
                'min' => new Expression("MIN($column)")
            ]);

        if ($where !== null) {
            $select->where($where);
        }

        $selectString = $sql->getSqlStringForSqlObject($select);

        $results = $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();

        return $results[0]['min'] ?? null;
    }

    /**
     * Return maximum value of a column.
     */
    public function getMax($column, $where = null)
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from($this->table)
            ->columns([
                'max' => new Expression("MAX($column)")
            ]);

        if ($where !== null) {
            $select->where($where);
        }

        $selectString = $sql->getSqlStringForSqlObject($select);

        $results = $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();

        return $results[0]['max'] ?? null;
    }

    /**
     * Return latest notifications (limit 4).
     */
    public function getLimit($param)
    {
        $where = is_array($param) ? $param : ['n.id' => $param];

        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from(['n' => $this->table])
            ->join(
                ['f' => 'sys_notification'],
                'f.id = n.notification',
                ['route', 'action', 'key', 'description']
            )
            ->where($where)
            ->order('n.id DESC')
            ->limit(4);

        $selectString = $sql->getSqlStringForSqlObject($select);

        return $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();
    }

    /**
     * Return record count.
     */
    public function getCount($where = null)
    {
        $adapter = $this->adapter;
        $sql = new Sql($adapter);

        $select = $sql->select();
        $select->from($this->table)
            ->columns([
                'count' => new Expression('COUNT(*)')
            ]);

        if ($where !== null) {
            $select->where($where);
        }

        $selectString = $sql->getSqlStringForSqlObject($select);

        $results = $adapter->query(
            $selectString,
            $adapter::QUERY_MODE_EXECUTE
        )->toArray();

        return (int)($results[0]['count'] ?? 0);
    }
}