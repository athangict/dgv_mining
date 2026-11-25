<?php
/**
 * Local Configuration Override
 */

use Laminas\DB\Adapter;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Doctirine\DBAL\Driver\PDOMysqlDriver as PDOMysqlDriver;

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};



return [
    'db' => [
        'driver' => 'Pdo',
        'dsn' => sprintf(
            'mysql:dbname=%s;hostname=%s;port=%s',
            $env('DB_NAME', 'dheyma_mining_24'),
            $env('DB_HOST', '127.0.0.1'),
            $env('DB_PORT', '3306')
        ),
        'driver-options' => [
            (defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
                ? Pdo\Mysql::ATTR_INIT_COMMAND
                : PDO::MYSQL_ATTR_INIT_COMMAND
            ) => "SET NAMES 'UTF8'",
        ],
        'username' => $env('DB_USER', 'root'),
        'password' => $env('DB_PASS', ''),
    ],
];