# Sparrow

Sparrow is a simple SQL builder and database abstraction layer.

## Building SQL

    // Include the library
    include '/path/to/sparrow.php';

    // Declare the class instance
    $db = new Sparrow();

    // Select a table
    $db->using('user')

    // Build a select query
    $db->select();

    // Display the SQL
    echo $db->sql();

Output:

    SELECT * FROM user

### Method Chaining

Sparrow allows you to chain methods together, so you can instead do:

    echo $db->using('user')->select()->sql();

### Where Conditions

To add where conditions to your query, use the `where` function.

    echo $db->using('user')
        ->where('id', 123)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 123

You can call where multiple times to add multiple conditions.

    echo $db->using('user')
        ->where('id', 123)
        ->where('name', 'bob')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 123 AND name = 'bob'

You can also pass an array to the where function. The following would produce the same output.

    $where = array('id' => 123, 'name' => 'bob');

    echo $db->using('user')
        ->where($where)
        ->select()
        ->sql();

You can even pass in a string literal.

    echo $db->using('user')
        ->where('id = 99')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 99

### Custom Operators

The default operator for where queries is `=`. You can use different operators by placing
them after the field declaration.

    echo $db->using('user')
        ->where('id >', 123)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id > 123;

### OR Queries

By default where conditions are joined together by `AND` keywords. To use OR instead, simply
place a `|` delimiter before the field name.

    echo $db->using('user')
        ->where('id <', 10)
        ->where('|id >', 20)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id < 10 OR id > 20

### LIKE Queries

To build a LIKE query you can use the special `%` operator.

    echo $db->using('user')
        ->where('name %', '%bob%')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE name LIKE '%bob%'

To build a NOT LIKE query, add a `!` before the `%` operator.

    echo $db->using('user')
        ->where('name !%', '%bob%')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE name NOT LIKE '%bob%'

### IN Queries

To use an IN statement in your where condition, user the special '@' operator
and pass in an array of values.

    echo $db->using('user')
        ->where('id @', array(10, 20, 30))
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id IN (10, 20, 30)

To build a NOT IN query, add a `!` before the `@` operator.

    echo $db->using('user')
        ->where('id !@', array(10, 20, 30))
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id NOT IN (10, 20, 30)

### Selecting Fields

To select specific fields, pass an array in to the `select` function.

    echo $db->using('user')
        ->select(array('id','name'))
        ->sql();

Output:

    SELECT id, name FROM user

### Limit and Offset

To add a limit or offset to a query, you can user the `limit` and `offset` functions.

    echo $db->using('user')
        ->limit(10)
        ->offset(20)
        ->select()
        ->sql();

Output:

    SELECT * FROM user LIMIT 10 OFFSET 20

You can also pass in additional parameters to the `select` function.

    echo $db->using('user')
        ->select('*', 50, 10)
        ->sql();

Output:

    SELECT * FROM user LIMIT 50 OFFSET 10

### Distinct

To add a DISTINCT keyword to your query, call the `distinct` function.

    echo $db->using('user')
        ->distinct()
        ->select('name')
        ->sql();

Output:

    SELECT DISTINCT name FROM user

### Table Joins

To add a table join, use the `join` function and pass in an array of fields to join on.

    echo $db->using('user')
        ->join('role', array('role.id' => 'user.id'))
        ->select()
        ->sql();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id

The default join type is an `INNER` join. To build other types of joins you can use
the alternate join functions `leftJoin`, `rightJoin`, and `fullJoin`.

The join array works just like where conditions, so you can use custom operators and add multiple conditions.

    echo $db->using('user')
        ->join('role', array('role.id' => 'user.id', 'role.id >' => 10))
        ->select()
        ->sql();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id AND role.id > 10

### Sorting

To add sorting to a query, user the `sortAsc` and `sortDesc` functions.

    echo $db->using('user')
        ->sortDesc('id')
        ->select()
        ->sql();

Output:

    SELECT * FROM user ORDER BY id DESC

You can also pass an array to the sort functions.

    echo $db->using('user')
        ->sortAsc(array('rank','name'))
        ->select()
        ->sql();

Output:

    SELECT * FROM user ORDER BY rank ASC, name ASC

### Grouping

To add a field to group by, use the `groupBy` function.

    echo $db->using('id')
        ->groupBy('points')
        ->select(array('id','count(*)'))
        ->sql();

Output:

    SELECT id, count(*) FROM user GROUP BY points;

### Insert Queries

To build an insert query, pass in an array of data to the `insert` function.

    $data = array('id' => 123, 'name' => 'bob');

    echo $db->using('user')
        ->insert($data)
        ->sql();

Output:

    INSERT INTO user (id, name) VALUES (123, 'bob')

### Update Queries

To build an update query, pass in an array of data to the `update` function.

    $data = array('name' => 'bob', 'email' => 'bob@aol.com');
    $where = array('id' => 123);

    echo $db->using('user')
        ->where($where)
        ->update($data)
        ->sql();

Output:

    UPDATE user SET name = 'bob', email = 'bob@aol.com' WHERE id = 123

### Delete Queries

To build a delete query, use the `delete` function.

    echo $db->using('user')
        ->where('id', 123)
        ->delete()
        ->sql();

Output:

    DELETE FROM user WHERE id = 123

## Executing Queries

Sparrow can also execute the queries it builds.
You just need to pass in a connection string to the class constructor:

    $db = new Sparrow('mysql://admin:pasSW0rd@localhost/mydb');

The connection string uses the following format:

    protocol://user:pass@hostname[:port]/dbname

The supported protocols are `mysql`, `mysqli`, `pgsql`, `sqlite`, and `sqlite3`.

### Fetching records

To fetch multiple records, use the `many` function.

    $rows = $db->using('user')
        ->where('id >', 100)
        ->many();

The result returned is an array of associative arrays:

    array(
        array('id' => 101, 'name' => 'joe'),
        array('id' => 102, 'name' => 'ted');
    )

To fetch a single record, use the `one` function.

    $row = $db->using('user')
        ->where('id', 123)
        ->one();

The result returned is a single associative array:

    array('id' => 123, 'name' => 'bob')

To fetch the value of a column, use the `value` function and pass in the name of the column.

    $username = $db->using('user')
        ->where('id', 123)
        ->value('username');

All the fetch functions automatically perform a select, so you don't need to include the `select` function
unless you want to specify the fields to return.

    $row = $db->using('user')
        ->where('id', 123)
        ->select(array('id', 'name'))
        ->one();

### Non-queries

For non-queries like update, insert and delete, use the `execute` function after building your query.

    $db->using('user')
        ->where('id', 123)
        ->delete()
        ->execute();

Executes:

    DELETE FROM user WHERE id = 123

### Custom Queries

You can also run raw SQL by calling any of the fetch or execute methods directly.

    $posts = $db->many('SELECT * FROM posts');

    $user = $db->one('SELECT * FROM user WHERE id = 123');

    $db->execute('UPDATE user SET name = 'bob' WHERE id = 1');

### Escaping Values

Sparrow's SQL building functions automatically escape values to prevent SQL injection.
To escape values manually, like when you're writing own queries, you can use the `escape` function.

    $name = "O'Dell";

    printf("SELECT * FROM user WHERE name = '%s'", $db->escape($name));

Output:

    SELECT * FROM user WHERE name = 'O\'Dell'

### Query Properties

After executing a query, several property values will be populated which you can access directly.

    // Last query executed
    $db->last_query;

    // Number of rows returned
    $db->num_rows;

    // Last insert id
    $db->insert_id;

    // Number of affected rows
    $db->affected_rows;

These values are reset every time a new query is executed.

### Helper Methods

To get a count of rows in a table.

    $count = $db->using('user')->count();

To get the minimum value from a table.

    $min = $db->using('employee')->min('salary');

To get the maximum value from a table.

    $max = $db->using('employee')->max('salary');

To get the average value from a table.

    $avg = $db->using('employee')->avg('salary');

To get the sum value from a table.

    $avg = $db->using('employee')->sum('salary');

### Direct Access

You can also access the database object directly by calling the  `getDB` function.

    $mysql = $db->getDB();

    mysql_info($mysql);

### Statistics

Sparrow has built in query statistics tracking. To enable it, just set the `stats_enabled` property.

    $db->stats_enabled = true;

After running your queries, get the stats array:

    $stats = $db->getStats();

The stats array contains the total time for all queries and an array of all queries executed
with individual query times.

    array(2) {
      ["query_time"]=>
          float(0.0013890266418457)
      ["queries"]=>
      array(2) {
        [0]=>
        array(2) {
          ["query"]=>
              string(34) "SELECT * FROM photo WHERE pid=1"
          ["time"]=>
              float(0.0010988712310791)
        }
        [1]=>
        array(2) {
          ["query"]=>
              string(32) "SELECT * FROM user WHERE uid=1"
          ["time"]=>
              float(0.0002901554107666)
        }
      }
    }

## License

Sparrow is released under the [MIT](http://www.opensource.org/licenses/mit-license.php) license.
