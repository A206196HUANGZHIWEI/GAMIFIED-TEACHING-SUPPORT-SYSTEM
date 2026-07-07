<?php

function db()
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $config = require __DIR__ . '/config.php';
    $dbConfig = array();

    if (is_array($config) && isset($config['db']) && is_array($config['db'])) {
        $dbConfig = $config['db'];
    } else {
        $dbConfig = array(
            'host' => isset($servername) ? $servername : (isset($host) ? $host : '127.0.0.1'),
            'port' => isset($port) ? $port : '3306',
            'database' => isset($dbname) ? $dbname : (isset($database) ? $database : ''),
            'username' => isset($username) ? $username : (isset($user) ? $user : ''),
            'password' => isset($password) ? $password : '',
            'charset' => isset($charset) ? $charset : 'utf8',
        );
    }

    $connection = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        (int) $dbConfig['port']
    );

    if ($connection->connect_errno) {
        http_response_code(500);
        exit('Database connection failed. Please check includes/config.php.');
    }

    $connection->set_charset($dbConfig['charset']);

    return $connection;
}
