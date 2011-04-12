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
    echo $db->select();

Output:

    SELECT * FROM user

### Where Conditions

To add where conditions to your query, use the `where` function.

    echo $db->using('user')->where('id', 123)->select();

Output:

    SELECT * FROM user WHERE id = 123

You can call where multiple times to add multiple conditions.

    echo $db->using('user')
        ->where('id', 123)
        ->where('name', 'bob')
        ->select();

Output:

    SELECT * FROM user WHERE id = 123 AND name = 'bob'

You can also pass an array to the where function. The following would produce the same output.

    $where = array('id' => 123, 'name' => 'bob');

    echo $db->using('user')->where($where)->select();

You can even pass in a string literal.

    echo $db->using('user')->where('id = 99')->select();

Output:

    SELECT * FROM user WHERE id = 99

### Custom Operators

The default operator for where queries is `=`. You can use different operators by placing
them after the field declaration.

    echo $db->using('user')->where('id >', 123)->select();

Output:

    SELECT * FROM user WHERE id > 123;

### OR Queries

By default where conditions are joined together by `AND` keywords. To use OR instead, simply
place a `|` delimiter before the field name.

    echo $db->using('user')
        ->where('id <', 10)
        ->where('|id >', 20)
        ->select();

Output:

    SELECT * FROM user WHERE id < 10 OR id > 20

### Where LIKE Queries

To build a LIKE query you can use the special `%` operator.

    echo $db->using('user')->where('name %', '%bob%')->select();

Output:

    SELECT * FROM user WHERE name LIKE '%bob%'

To build a NOT LIKE query, add a `!` before the `%` operator.

    echo $db->using('user')->where('name !%', '%bob%')->select();

Output:

    SELECT * FROM user WHERE name NOT LIKE '%bob%'

### Where IN Queries

To use an IN statement in your where condition, user the special '@' operator
and pass in and array of values.

    echo $db->using('user')->where('id @', array(10, 20, 30))->select();

Output:

    SELECT * FROM user WHERE id IN (10, 20, 30)

To build a NOT IN query, add a `!` before the `@` operator.

    echo $db->using('user')->where('id !@', array(10, 20, 30))->select();

Output:

    SELECT * FROM user WHERE id NOT IN (10, 20, 30)

### Selecting Fields

To select specific fields, pass an array in to the `select` function.

    echo $db->using('user')->select(array('id','name'));

Output:

    SELECT id, name FROM user

### Limit and Offset

To add a limit or offset to a query, pass in additional parameters to the `select` function.

    echo $db->using('user')->select('*', 10, 20);

Output:

    SELECT * FROM user LIMIT 10 OFFSET 20

### Distinct

To add a DISTINCT keyword to your query, call the `distinct` function.

    echo $db->using('user')->distinct()->select('name');

Output:

    SELECT DISTINCT name FROM user

### Table Joins

To add a table join, use the `join` function and pass in an array of fields to join on.

    echo $db->using('user')
        ->join('role', array('role.id' => 'user.id'))
        ->select();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id

To build other types of joins you can use the alternate join functions `leftJoin`, `rightJoin`, and `fullJoin`.

The join array works just like where conditions.

    echo $db->using('user')
        ->join('role', array('role.id' => 'user.id', 'role.id >' => 10))
        ->select();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id AND role.id > 10

### Sorting

To add sorting to a query, user the `sortAsc` and `sortDesc` functions.

    echo $db->using('user')->sortDesc('id')->select();

Output:

    SELECT * FROM user ORDER BY id DESC

### Grouping

To add a field to group by, use the `groupBy` function.

    echo $db->using('id')->groupBy('points')->select(array('id','count(*)'));

Output:

    SELECT id, count(*) FROM user GROUP BY points;

### Insert Queries

To build an insert query, pass in an array of data to the `insert` function.

    $data = array('id' => 123, 'name' => 'bob');

    echo $db->using('user')->insert($data);

Output:

    INSERT INTO user (id, name) VALUES (123, 'bob')

### Update Queries

To build an update query, pass in an array of data to the `update` function.

    $data = array('name' => 'bob', 'email' => 'bob@aol.com');
    $where = array('id' => 123);

    echo $db->using('user')
        ->where($where)
        ->update($data);

Output:

    UPDATE user SET name = 'bob', email = 'bob@aol.com' WHERE id = 123

### Delete Queries

To build a delete query, use the `delete` function.

    echo $db->using('user')->where('id', 123)->delete();

Output:

    DELETE FROM user WHERE id = 123

## Executing Queries

Sparrow can also execute the queries it builds. Just declare a connection string:

    $db = new Sparrow('mysql://user:pass@localhost/dbname');

The connection string uses the following format:

    protocol://user:pass@hostname[:port]/dbname

The supported protocols are `mysql`, `mysqli`, `sqlite`, and `sqlite3`.

### Fetching records

To fetch multiple records, use the `fetch` function.

    $db->using('user')->where('id >', 100)->fetch();

To fetch a single record, user the `fetchRow` function.

    $db->using('user')->where('id', 123)->fetchRow();

To fetch the value of a column, use the `fetchColumn` function and passing the name of the column.

    $username = $db->using('user')->where('id', 123)->fetchColumn('username');

### Non-queries

For non-queries like update, insert and delete, you use the `execute` function.

    $sql = $db->using('user')->where('id', 123)->delete();

    $db->execute($sql);

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

### Statistics

Sparrow has built in query statistics tracking. To enable it, just set the property.

    $db->stats_enabled = true;

After running your queries, get the stats array:

    $stats = $db->getStats();

The stats array contains the total time for all queries, an array of all queries executed
with individual query times.