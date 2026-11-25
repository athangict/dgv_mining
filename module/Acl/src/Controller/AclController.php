<?php

namespace Acl\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model as Acl;

class AclController extends AbstractActionController
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
    protected $_highest_role;
    protected $_lowest_role;

    // ✅ PHP 8.2 FIX: prevent dynamic property deprecation
    protected $_connection;
    protected $_safedataObj;
    protected $_permissionObj;

    public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }

    public function getDefaultTable($table)
    {
        $this->_table = new TableGateway(
            $table,
            $this->_container->get('Laminas\Db\Adapter\Adapter')
        );
        return $this->_table;
    }

    public function getDefinedTable($table)
    {
        return $this->_container->get($table);
    }

    public function init()
    {
        $this->_auth = new AuthenticationService();

        if (!$this->_auth->hasIdentity()) {
            $this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
            return $this->redirect()->toRoute('auth', ['action' => 'login']);
        }

        $this->_config = $this->_container->get('Config');
        $this->_user = $this->identity();

        $this->_login_id = $this->_user->id ?? null;
        $this->_login_role = $this->_user->role ?? null;
        $this->_author = $this->_user->id ?? null;

        $rolesTable = $this->getDefinedTable(Acl\RolesTable::class);

        $this->_highest_role = $rolesTable->getMax('id');
        $this->_lowest_role  = $rolesTable->getMin('id');

        $this->_id = $this->params()->fromRoute('id');

        $this->_created = date('Y-m-d H:i:s');
        $this->_modified = date('Y-m-d H:i:s');

        $this->_safedataObj = $this->safedata();

        $this->_connection = $this->_container
            ->get('Laminas\Db\Adapter\Adapter')
            ->getDriver()
            ->getConnection();
    }

    public function indexAction()
    {
        $this->init();

        return new ViewModel([
            'title' => 'Acl Menu',
        ]);
    }

    public function rolelistAction()
    {
        $this->init();

        $roleTable = $this->getDefinedTable(Acl\RolesTable::class)->getAll();

        $paginator = new \Laminas\Paginator\Paginator(
            new \Laminas\Paginator\Adapter\ArrayAdapter($roleTable)
        );

        $page = $this->params()->fromRoute('page', 1);

        $paginator->setCurrentPageNumber((int)$page);
        $paginator->setItemCountPerPage(20);
        $paginator->setPageRange(8);

        return new ViewModel([
            'title'     => 'Role Management',
            'paginator' => $paginator,
            'page'      => $page,
        ]);
    }

    public function addrolelistAction()
    {
        $this->init();

        $page = $this->_id;

        if ($this->getRequest()->isPost()) {

            $form = $this->getRequest()->getPost();

            $thirdmax_id = $this->getDefinedTable(Acl\RolesTable::class)->getMaxLimitOffset('id');
            $next_id = $thirdmax_id + 1;
            $max_id = $this->getDefinedTable(Acl\RolesTable::class)->getMax('id');

            if ($next_id < $max_id && !in_array($next_id, [100, 99])) {

                $data = [
                    'id'          => $next_id,
                    'role'        => $form['role'],
                    'description' => $form['description'],
                    'status'      => $form['status'],
                    'author'      => $this->_author,
                    'created'     => $this->_created,
                    'modified'    => $this->_modified,
                ];

                $data = $this->_safedataObj->rteSafe($data);

                $this->_connection->beginTransaction();

                $result = $this->getDefinedTable(Acl\RolesTable::class)
                    ->save($data, 'insert');

                if ($result) {
                    $this->_connection->commit();
                } else {
                    $this->_connection->rollback();
                }
            }

            return $this->redirect()->toRoute('acl/paginator', [
                'action' => 'rolelist',
                'page'   => $this->_id,
            ]);
        }

        return new ViewModel([
            'title'        => 'Add Role',
            'page'         => $page,
            'highest_role' => $this->_highest_role,
        ]);
    }

    public function editrolelistAction()
    {
        $this->init();

        $id = $this->_id;
        $array_id = explode("_", $id);

        $role_id = $array_id[0];
        $page = $array_id[1] ?? '';

        if ($this->getRequest()->isPost()) {

            $form = $this->getRequest()->getPost();

            $data = [
                'id'          => $form['role_id'],
                'role'        => $form['role'],
                'description' => $form['description'],
                'status'      => $form['status'],
                'author'      => $this->_author,
                'modified'    => $this->_modified,
            ];

            $data = $this->_safedataObj->rteSafe($data);

            $this->_connection->beginTransaction();

            $result = $this->getDefinedTable(Acl\RolesTable::class)
                ->save($data, 'update');

            $result
                ? $this->_connection->commit()
                : $this->_connection->rollback();

            return $this->redirect()->toRoute('acl/paginator', [
                'action' => 'rolelist',
                'page'   => $this->_id,
            ]);
        }

        return new ViewModel([
            'title'   => 'Edit Role',
            'page'    => $page,
            'role_id' => $role_id,
            'rolesObj'=> $this->getDefinedTable(Acl\RolesTable::class),
        ]);
    }

   public function mapmodulesAction()
{
    $this->init();

    $id = $this->_id ?? '';

$array_id = explode('_', $id);

$role_id = $array_id[0] ?? 0;
$page    = $array_id[1] ?? 1;

    $role   = $role_id;
    $module = $this->params()->fromRoute('role');
    $task   = $this->params()->fromRoute('task');

    // Handle map/unmap request
    if ($task && $role > 0 && $module > 0) {

        $data = [
            'role'     => $role,
            'module'   => $module,
            'author'   => $this->_author,
            'created'  => $this->_created,
            'modified' => $this->_modified,
        ];

        if ($task == 'map') {
            $this->getDefinedTable(Acl\RolemoduleTable::class)->save($data);
        }

        if ($task == 'unmap') {
            $this->getDefinedTable(Acl\RolemoduleTable::class)->remove($data);
        }

        return $this->redirect()->toRoute('acl/paginator', [
            'action' => 'mapmodules',
            'id'     => $role . '_' . $page,
            'role'   => 0,
            'task'   => 0,
        ]);
    }

    // Get roles
    $roles = $this->getDefinedTable(Acl\RolesTable::class)->getAll();

    // Create paginator
    $paginator = new \Laminas\Paginator\Paginator(
        new \Laminas\Paginator\Adapter\ArrayAdapter($roles)
    );

    $paginator->setCurrentPageNumber((int)$page);
    $paginator->setItemCountPerPage(20);
    $paginator->setPageRange(8);

    return new ViewModel([
        'title'         => 'Role Module Privileges',
        'roles'         => $paginator,
        'page'          => $page,
        'moduleObj'     => $this->getDefinedTable(Acl\ModuleTable::class),
        'rolemoduleObj' => $this->getDefinedTable(Acl\RolemoduleTable::class),
    ]);
}

  public function permissionAction()
{
    $this->init();

    $data = [
        'resource' => $this->params()->fromRoute('id', '-1'),
        'process'  => '-1',
        'role'     => '-1'
    ];

    // Get modules
    $modules = $this->getDefinedTable(Acl\ModuleTable::class)->getAll();

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        return $this->jsonResponse([
            'result' => 'ok'
        ]);
    }

    return new ViewModel([
        'title'         => 'Role Permission Manager',
        'data'          => $data,
        'modules'       => $modules,

        // IMPORTANT: add missing objects used in view
        'roleObj'       => $this->getDefinedTable(Acl\RolesTable::class),
        'processObj'    => $this->getDefinedTable(Acl\ProcessTable::class),
        'aclObj'        => $this->getDefinedTable(Acl\AclTable::class),
        'roleprocessObj'=> $this->getDefinedTable(Acl\RoleprocessTable::class),
        'roleaclObj'    => $this->getDefinedTable(Acl\RoleaclTable::class),
    ]);
}

    public function jsonResponse($data)
    {
        echo json_encode($data);
        exit;
    }
}