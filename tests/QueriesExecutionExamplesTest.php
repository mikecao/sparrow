<?php

use PHPUnit\Framework\TestCase;
use QueryBuilderExamplesTest as Test;

class QueriesExecutionExamplesTest extends TestCase {
  function testCanConnectToMysqlDatabaseFromConnectionString() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb('mysql://root:root@localhost/test');

    self::assertInstanceOf('mysqli', $sparrow->getDb());
  }

  function testCanConnectToMysqlDatabaseFromConnectionArray() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb(array(
      'type' => 'mysql',
      'hostname' => 'localhost',
      'database' => 'test',
      'username' => 'root',
      'password' => 'root',
      'port' => 3306
    ));

    self::assertInstanceOf('mysqli', $sparrow->getDb());
  }

  function testCanConnectToMysqlDatabaseFromAConnectionObject() {
    $mysql = new mysqli('localhost', 'root', 'root');
    $mysql->select_db('test');

    $sparrow = Test::sparrow(true);
    $sparrow->setDb($mysql);

    self::assertInstanceOf('mysqli', $sparrow->getDb());
  }

  function testCanConnectToMysqlDatabaseFromAPdoConnectionString() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb('pdomysql://root:root@localhost/test');

    self::assertInstanceOf('PDO', $sparrow->getDb());
  }

  function testCanConnectToMysqlDatabaseFromAPdoObject() {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');

    $sparrow = Test::sparrow(true);
    $sparrow->setDb($pdo);

    self::assertInstanceOf('PDO', $sparrow->getDb());
  }

  function testCanConnectToSqliteDatabaseFromConnectionString() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb('sqlite://' . __DIR__ . '/test.db');

    self::assertInstanceOf('SQLite3', $sparrow->getDb());
  }

  function testCanConnectToSqliteDatabaseFromPdoConnectionString() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb('pdosqlite://' . __DIR__ . '/test.db');

    self::assertInstanceOf('PDO', $sparrow->getDb());
  }
}
