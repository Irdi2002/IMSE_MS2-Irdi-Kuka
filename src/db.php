<?php
require_once '/var/www/html/vendor/autoload.php';

function getMongoDb(): MongoDB\Database {
    static $db = null;
    if ($db === null) {
        $mongoUri   = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
        $client = new MongoDB\Client($mongoUri);
        $db = $client->selectDatabase('IMSE_MS2');
    }
    return $db;
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = 'MySQLDockerContainer';
        $db   = 'IMSE_MS2';
        $user = 'root';
        $pass = 'IMSEMS2';
        $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function getMySQLi(): mysqli {
    static $mysqli = null;
    if ($mysqli === null) {
        $mysqli = new mysqli('MySQLDockerContainer', 'root', 'IMSEMS2', 'IMSE_MS2');
        if ($mysqli->connect_error) {
            throw new Exception('MySQL connection error: ' . $mysqli->connect_error);
        }
    }
    return $mysqli;
}
