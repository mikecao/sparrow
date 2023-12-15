<?php

use PHPUnit\Framework\TestCase;

class QueryBuilderExamplesTest extends TestCase {
  function testCanCreateASparrowInstance() {
    $sparrow = new Sparrow;

    self::assertInstanceOf('Sparrow', $sparrow);
  }

  function testCanBuildASelectAllQuery() {
    $result = self::sparrow()->select()->sql();
    $expected = 'SELECT * FROM user';

    self::assertSame($expected, $result);
  }

  function testCanBuildASingleWhereCondition() {
    $result = self::sparrow()->where('id', 123)->select()->sql();
    $expected = 'SELECT * FROM user WHERE id=123';

    self::assertSame($expected, $result);
  }

  function testCanBuildMultipleWhereConditions() {
    $result = self::sparrow()
      ->where('id', 123)
      ->where('name', 'bob')
      ->select()
      ->sql();

    $expected = "SELECT * FROM user WHERE id=123 AND name='bob'";

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionFromAnArray() {
    $where = array('id' => 123, 'name' => 'bob');
    $result = self::sparrow()->where($where)->select()->sql();
    $expected = "SELECT * FROM user WHERE id=123 AND name='bob'";

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionFromAString() {
    $result = self::sparrow()->where('id = 99')->select()->sql();
    $expected = 'SELECT * FROM user WHERE id = 99';

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionWithACustomOperator() {
    $result = self::sparrow()->where('id >', 123)->select()->sql();
    $expected = 'SELECT * FROM user WHERE id>123';

    self::assertSame($expected, $result);
  }

  function testCanBuildAnOrWhereCondition() {
    $result = self::sparrow()
      ->where('id <', 10)
      ->where('|id >', 20)
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user WHERE id<10 OR id>20';

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionWithLikeOperator() {
    $result = self::sparrow()
      ->where('name %', '%bob%')
      ->select()
      ->sql();

    $expected = "SELECT * FROM user WHERE name LIKE '%bob%'";

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionWithANotLikeOperator() {
    $result = self::sparrow()
      ->where('name !%', '%bob%')
      ->select()
      ->sql();

    $expected = "SELECT * FROM user WHERE name NOT LIKE '%bob%'";

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionWithInOperator() {
    $result = self::sparrow()
      ->where('id @', array(10, 20, 30))
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user WHERE id IN (10,20,30)';

    self::assertSame($expected, $result);
  }

  function testCanBuildAWhereConditionWithNotInOperator() {
    $result = self::sparrow()
      ->where('id !@', array(10, 20, 30))
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user WHERE id NOT IN (10,20,30)';

    self::assertSame($expected, $result);
  }

  function testCanBuildASelectQueryWithSpecifiedFields() {
    $result = self::sparrow()->select(array('id', 'name'))->sql();
    $expected = 'SELECT id,name FROM user';

    self::assertSame($expected, $result);
  }

  function testCanBuildASelectQueryWithALimitAndOffset() {
    $result = self::sparrow()->limit(10)->offset(20)->select()->sql();
    $expected = 'SELECT * FROM user LIMIT 10 OFFSET 20';

    self::assertSame($expected, $result);
  }

  function testCanBuildASelectQueryWithALimitAndOffsetFromTheSelectMethod() {
    $result = self::sparrow()->select('*', 50, 10)->sql();
    $expected = 'SELECT * FROM user LIMIT 50 OFFSET 10';

    self::assertSame($expected, $result);
  }

  function testCanBuildASelectQueryWithADistinctField() {
    $result = self::sparrow()->distinct()->select('name')->sql();
    $expected = 'SELECT DISTINCT name FROM user';

    self::assertSame($expected, $result);
  }

  function testCanBuildASimpleTableJoinQuery() {
    $result = self::sparrow()
      ->join('role', array('role.id' => 'user.role_id'))
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user  INNER JOIN role ON role.id=user.role_id';

    self::assertSame($expected, $result);
  }

  function testCanBuildATableJoinQueryWithMultipleConditionsAndCustomOperators() {
    $result = self::sparrow()
      ->join('role', array('role.id' => 'user.role_id', 'role.id >' => 10))
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user  INNER JOIN role ON role.id=user.role_id AND role.id>10';

    self::assertSame($expected, $result);
  }

  function testCanBuildADescSortedSelectQuery() {
    $result = self::sparrow()
      ->sortDesc('id')
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user ORDER BY id DESC';

    self::assertSame($expected, $result);
  }

  function testCanBuildAnAscSortedMultipleFieldsSelectQuery() {
    $result = self::sparrow()
      ->sortAsc(array('rank', 'name'))
      ->select()
      ->sql();

    $expected = 'SELECT * FROM user ORDER BY rank ASC, name ASC';

    self::assertSame($expected, $result);
  }

  function testCanBuildASimpleGroupBySelectQuery() {
    $result = self::sparrow()
      ->groupBy('points')
      ->select(array('id', 'count(*)'))
      ->sql();

    $expected = 'SELECT id,count(*) FROM user GROUP BY points';

    self::assertSame($expected, $result);
  }

  function testCanBuildASimpleInsertQuery() {
    $data = array('id' => 123, 'name' => 'bob');
    $result = self::sparrow()
      ->insert($data)
      ->sql();

    $expected = "INSERT INTO user (id,name) VALUES (123,'bob')";

    self::assertSame($expected, $result);
  }

  function testCanBuildASimpleUpdateQuery() {
    $data = array('name' => 'bob', 'email' => 'bob@aol.com');
    $where = array('id' => 123);

    $result = self::sparrow()
      ->where($where)
      ->update($data)
      ->sql();

    $expected = "UPDATE user SET name='bob',email='bob@aol.com' WHERE id=123";

    self::assertSame($expected, $result);
  }

  function testCanBuildASimpleDeleteQuery() {
    $result = self::sparrow()
      ->where('id', 123)
      ->delete()
      ->sql();

    $expected = "DELETE FROM user WHERE id=123";

    self::assertSame($expected, $result);
  }

  static function sparrow($new = false) {
    static $sparrow = null;

    if ($sparrow === null) {
      $sparrow = new Sparrow;
    }

    if ($new === true) {
      return clone $sparrow->from('user');
    }

    return $sparrow->from('user');
  }
}
