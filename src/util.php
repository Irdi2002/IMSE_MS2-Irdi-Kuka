<?php
namespace App;

/**
 * Build a MySQL DSN string using host and database name.
 */
function build_mysql_dsn(string $host, string $db): string
{
    return "mysql:host=$host;dbname=$db;charset=utf8mb4";
}
