<?php

use PHPUnit\Framework\TestCase;
use QueryBuilderExamplesTest as Test;

class QueriesExecutionExamplesTest extends TestCase {
  function testCanFetchMultipleRecords() {
    $details = self::sparrow()->where('OrderDetailID >', 100)->many();

    $expected = array(
      'OrderDetailID' => 101,
      'OrderID' => 10285,
      'ProductID' => 40,
      'Quantity' => 40
    );

    self::assertCount(418, $details);
    self::assertSame($expected, $details[0]);
  }

  function testCanFetchOneRecord() {
    $details = self::sparrow()->where('OrderDetailID', 123)->one();

    $expected = array(
      'OrderDetailID' => 123,
      'OrderID' => 10293,
      'ProductID' => 75,
      'Quantity' => 6
    );

    self::assertSame($expected, $details);
  }

  function testCanFetchASingleRecordValue() {
    $orderID = self::sparrow()->where('OrderDetailID', 123)->value('OrderID');

    self::assertSame(10293, $orderID);
  }

  function testCanFetchOneRecordWithSpecifiedFields() {
    $details = self::sparrow()
      ->where('OrderDetailID', 123)
      ->select(array('OrderDetailID', 'OrderID'))
      ->one();

    $expected = array('OrderDetailID' => 123, 'OrderID' => 10293);

    self::assertSame($expected, $details);
  }

  function testCanDeleteOneRecord() {
    $sparrow = self::sparrow();
    $sparrow
      ->sql(file_get_contents(__DIR__ . '/UsersTable.sql'))
      ->execute();

    $userID = hash('sha256', rand());
    $data = array('UserID' => $userID, 'UserName' => 'Franyer');

    $sparrow
      ->from('Users')
      ->insert($data)
      ->execute();

    $userInserted = self::sparrow()
      ->from('Users')
      ->where('UserID', $userID)
      ->one();

    self::assertSame($data, $userInserted);

    $sparrow
      ->delete(array('UserID' => $userID))
      ->execute();

    $lastSQL = $sparrow->sql();
    $expected = "DELETE FROM Users WHERE UserID='$userID'";

    self::assertSame($lastSQL, $expected);

    $mustBeEmpty = $sparrow->where('UserID', $userID)->one();

    self::assertSame(array(), $mustBeEmpty);
  }

  function testCanExecuteCustomQueries() {
    $sparrow = self::sparrow();
    $categories = $sparrow->sql('SELECT * FROM Categories')->many();

    $expected = array(
      'CategoryID' => 1,
      'CategoryName' => 'Beverages',
      'Description' => 'Soft drinks, coffees, teas, beers, and ales'
    );

    self::assertCount(8, $categories);
    self::assertSame($expected, $categories[0]);

    $category = $sparrow->sql('SELECT * FROM Categories WHERE CategoryID = 1')->one();

    self::assertSame($expected, $category);

    $userID = hash('sha256', rand());
    $data = array('UserID' => $userID, 'UserName' => 'Franyer');

    self::sparrow()
      ->from('Users')
      ->insert($data)
      ->execute();

    $sparrow
      ->sql("UPDATE Users SET UserName = 'Adrián' WHERE UserID = '$userID'")
      ->execute();

    $userUpdated = $sparrow->sql("SELECT UserName FROM Users WHERE UserID = '$userID'")->one();

    self::assertSame('Adrián', $userUpdated['UserName']);

    $sparrow->sql("DELETE FROM Users WHERE UserID = '$userID'")->execute();
  }

  function testCanEscapeSpecialCharacters() {
    $sparrow = self::sparrow();
    $name = "O'Dell";

    $result = sprintf('SELECT * FROM user WHERE name = %s', $sparrow->quote($name));
    $expected = "SELECT * FROM user WHERE name = 'O\'Dell'";

    self::assertSame($expected, $result);
  }

  function testCanFillQueryProperties() {
    $sparrow = self::sparrow();
    $sql = 'SELECT * FROM Categories';

    $sparrow->sql($sql)->many();
    $sparrow->sql('CREATE TABLE test (id PRIMARY KEY AUTOINCREMENT)');

    self::assertSame($sql, $sparrow->last_query);
    self::assertSame(8, $sparrow->num_rows);
  }

  private static function sparrow() {
    $sparrow = Test::sparrow(true);
    $sparrow->setDb('sqlite://' . __DIR__ . '/Northwind.db');
    $sparrow->from('OrderDetails');

    return $sparrow;
  }
}
