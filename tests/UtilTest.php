<?php
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    public function testBuildMysqlDsn()
    {
        $dsn = App\build_mysql_dsn('localhost', 'testdb');
        $this->assertSame('mysql:host=localhost;dbname=testdb;charset=utf8mb4', $dsn);
    }
}
