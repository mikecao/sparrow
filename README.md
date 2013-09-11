# Sparrow

Sparrow is a simple but powerful database toolkit. Sparrow is a fluent SQL builder, database abstraction layer, cache manager,
query statistics generator, and micro-ORM all rolled into a single class file.

## Building SQL

    // Include the library
    include '/path/to/sparrow.php';

    // Declare the class instance
    $db = new Sparrow();

    // Select a table
    $db->from('user')

    // Build a select query
    $db->select();

    // Display the SQL
    echo $db->sql();

Output:

    SELECT * FROM user

### Method Chaining

Sparrow allows you to chain methods together, so you can instead do:

    echo $db->from('user')->select()->sql();

### Where Conditions

To add where conditions to your query, use the `where` function.

    echo $db->from('user')
        ->where('id', 123)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 123

You can call where multiple times to add multiple conditions.

    echo $db->from('user')
        ->where('id', 123)
        ->where('name', 'bob')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 123 AND name = 'bob'

You can also pass an array to the where function. The following would produce the same output.

    $where = array('id' => 123, 'name' => 'bob');

    echo $db->from('user')
        ->where($where)
        ->select()
        ->sql();

You can even pass in a string literal.

    echo $db->from('user')
        ->where('id = 99')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id = 99

### Custom Operators

The default operator for where queries is `=`. You can use different operators by placing
them after the field declaration.

    echo $db->from('user')
        ->where('id >', 123)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id > 123;

### OR Queries

By default where conditions are joined together by `AND` keywords. To use OR instead, simply
place a `|` delimiter before the field name.

    echo $db->from('user')
        ->where('id <', 10)
        ->where('|id >', 20)
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id < 10 OR id > 20

### LIKE Queries

To build a LIKE query you can use the special `%` operator.

    echo $db->from('user')
        ->where('name %', '%bob%')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE name LIKE '%bob%'

To build a NOT LIKE query, add a `!` before the `%` operator.

    echo $db->from('user')
        ->where('name !%', '%bob%')
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE name NOT LIKE '%bob%'

### IN Queries

To use an IN statement in your where condition, use the special `@` operator
and pass in an array of values.

    echo $db->from('user')
        ->where('id @', array(10, 20, 30))
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id IN (10, 20, 30)

To build a NOT IN query, add a `!` before the `@` operator.

    echo $db->from('user')
        ->where('id !@', array(10, 20, 30))
        ->select()
        ->sql();

Output:

    SELECT * FROM user WHERE id NOT IN (10, 20, 30)

### Selecting Fields

To select specific fields, pass an array in to the `select` function.

    echo $db->from('user')
        ->select(array('id','name'))
        ->sql();

Output:

    SELECT id, name FROM user

### Limit and Offset

To add a limit or offset to a query, you can use the `limit` and `offset` functions.

    echo $db->from('user')
        ->limit(10)
        ->offset(20)
        ->select()
        ->sql();

Output:

    SELECT * FROM user LIMIT 10 OFFSET 20

You can also pass in additional parameters to the `select` function.

    echo $db->from('user')
        ->select('*', 50, 10)
        ->sql();

Output:

    SELECT * FROM user LIMIT 50 OFFSET 10

### Distinct

To add a DISTINCT keyword to your query, call the `distinct` function.

    echo $db->from('user')
        ->distinct()
        ->select('name')
        ->sql();

Output:

    SELECT DISTINCT name FROM user

### Table Joins

To add a table join, use the `join` function and pass in an array of fields to join on.

    echo $db->from('user')
        ->join('role', array('role.id' => 'user.id'))
        ->select()
        ->sql();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id

The default join type is an `INNER` join. To build other types of joins you can use
the alternate join functions `leftJoin`, `rightJoin`, and `fullJoin`.

The join array works just like where conditions, so you can use custom operators and add multiple conditions.

    echo $db->from('user')
        ->join('role', array('role.id' => 'user.id', 'role.id >' => 10))
        ->select()
        ->sql();

Output:

    SELECT * FROM user INNER JOIN role ON role.id = user.id AND role.id > 10

### Sorting

To add sorting to a query, use the `sortAsc` and `sortDesc` functions.

    echo $db->from('user')
        ->sortDesc('id')
        ->select()
        ->sql();

Output:

    SELECT * FROM user ORDER BY id DESC

You can also pass an array to the sort functions.

    echo $db->from('user')
        ->sortAsc(array('rank','name'))
        ->select()
        ->sql();

Output:

    SELECT * FROM user ORDER BY rank ASC, name ASC

### Grouping

To add a field to group by, use the `groupBy` function.

    echo $db->from('user')
        ->groupBy('points')
        ->select(array('id','count(*)'))
        ->sql();

Output:

    SELECT id, count(*) FROM user GROUP BY points;

### Insert Queries

To build an insert query, pass in an array of data to the `insert` function.

    $data = array('id' => 123, 'name' => 'bob');

    echo $db->from('user')
        ->insert($data)
        ->sql();

Output:

    INSERT INTO user (id, name) VALUES (123, 'bob')

### Update Queries

To build an update query, pass in an array of data to the `update` function.

    $data = array('name' => 'bob', 'email' => 'bob@aol.com');
    $where = array('id' => 123);

    echo $db->from('user')
        ->where($where)
        ->update($data)
        ->sql();

Output:

    UPDATE user SET name = 'bob', email = 'bob@aol.com' WHERE id = 123

### Delete Queries

To build a delete query, use the `delete` function.

    echo $db->from('user')
        ->where('id', 123)
        ->delete()
        ->sql();

Output:

    DELETE FROM user WHERE id = 123

## Executing Queries

Sparrow can also execute the queries it builds. You will need to call the `setDb()` method with either
a connection string, an array of connection information, or a connection object.

The supported database types are `mysql`, `mysqli`, `pgsql`, `sqlite` and `sqlite3`.

Using a connection string:

    $db->setDb('mysql://admin:hunter2@localhost/mydb');

The connection string uses the following format:

    type://username:password@hostname[:port]/database

For sqlite, you need to use:

    type://database

Using a connection array:

    $db->setDb(array(
        'type' => 'mysql',
        'hostname' => 'localhost',
        'database' => 'mydb',
        'username' => 'admin',
        'password' => 'hunter2'
    ));

The possible array options are `type`, `hostname`, `database`, `username`, `password`, and `port`.

Using a connection object:

    $mysql = mysql_connect('localhost', 'admin', 'hunter2');

    mysql_select_db('mydb');

    $db->setDb($mysql);

You can also use PDO for the database connection. To use the connection string or array method, prefix the database type with `pdo`:

    $db->setDb('pdomysql://admin:hunter2@localhost/mydb');

The possible PDO types are `pdomysql`, `pdopgsql`, and `pdosqlite`.

You can also pass in any PDO object directly:

    $pdo = new PDO('mysql:host=localhost;dbname=mydb', 'admin', 'hunter2');

    $db->setDb($pdo);

### Fetching records

To fetch multiple records, use the `many` function.

    $rows = $db->from('user')
        ->where('id >', 100)
        ->many();

The result returned is an array of associative arrays:

    array(
        array('id' => 101, 'name' => 'joe'),
        array('id' => 102, 'name' => 'ted');
    )

To fetch a single record, use the `one` function.

    $row = $db->from('user')
        ->where('id', 123)
        ->one();

The result returned is a single associative array:

    array('id' => 123, 'name' => 'bob')

To fetch the value of a column, use the `value` function and pass in the name of the column.

    $username = $db->from('user')
        ->where('id', 123)
        ->value('username');

All the fetch functions automatically perform a select, so you don't need to include the `select` function
unless you want to specify the fields to return.

    $row = $db->from('user')
        ->where('id', 123)
        ->select(array('id', 'name'))
        ->one();

### Non-queries

For non-queries like update, insert and delete, use the `execute` function after building your query.

    $db->from('user')
        ->where('id', 123)
        ->delete()
        ->execute();

Executes:

    DELETE FROM user WHERE id = 123

### Custom Queries

You can also run raw SQL by passing it to the `sql` function.

    $posts = $db->sql('SELECT * FROM posts')->many();

    $user = $db->sql('SELECT * FROM user WHERE id = 123')->one();

    $db->sql('UPDATE user SET name = 'bob' WHERE id = 1')->execute();

### Escaping Values

Sparrow's SQL building functions automatically quote and escape values to prevent SQL injection.
To quote and escape values manually, like when you're writing own queries, you can use the `quote` function.

    $name = "O'Dell";

    printf("SELECT * FROM user WHERE name = %s", $db->quote($name));

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

    $count = $db->from('user')->count();

To get the minimum value from a table.

    $min = $db->from('employee')->min('salary');

To get the maximum value from a table.

    $max = $db->from('employee')->max('salary');

To get the average value from a table.

    $avg = $db->from('employee')->avg('salary');

To get the sum value from a table.

    $avg = $db->from('employee')->sum('salary');

### Direct Access

You can also access the database object directly by using the  `getDb` function.

    $mysql = $db->getDb();

    mysql_info($mysql);

## Caching

To enable caching, you need to use the `setCache` method with a connection string or connection object.

Using a connection string:

    $db->setCache('memcache://localhost:11211');

Using a cache object:

    $cache = new Memcache();
    $cache->addServer('localhost', 11211);

    $db->setCache($cache);

You can then pass a cache key to the query functions and Sparrow will try to fetch from the cache before
executing the query. If there is a cache miss, Sparrow will execute the query and store the results
using the specified cache key.

    $key = 'all_users';

    $users = $db->from('user')->many($key);

### Cache Types

The supported caches are `memcache`, `memcached`, `apc`, `xcache`, `file` and `memory`.

To use `memcache` or `memcached`, you need to use the following connection string:

    protocol://hostname:port

To use `apc` or `xcache`, just pass in the cache name:

    $db->setCache('apc');

To use the filesystem as a cache, pass in a directory path:

    $db->setCache('/usr/local/cache');

    $db->setCache('./cache');

Note that local directories must be prefixed with `./`.

The default cache is `memory` and only lasts the duration of the script.

### Cache Expiration

To cache data only for a set period of time, you can pass in an additional parameter which represents the expiraton time in seconds.

    $key = 'top_users';
    $expire = 600;

    $users = $db->from('user')
        ->sortDesc('score')
        ->limit(100)
        ->many($key, $expire);

In the above example, we are getting a list of the top 100 highest scoring users and caching it for 600 seconds (10 minutes).
You can pass the expiration parameter to any of the query methods that take a cache key parameter.

### Direct Access

You can access the cache object directly by using the `getCache` function.

    $memcache = $db->getCache();

    echo $memcache->getVersion();

You can manipulate the cache data directly as well. To cache a value use the `store` function.

    $db->store('id', 123);

To retrieve a cached value use the `fetch` function.

    $id = $db->fetch('id');

To delete a cached value use the `clear` function.

    $db->clear('id');

To completely empty the cache use the `flush` function.

    $db->flush();

## Using Objects

Sparrow also provides some functionality for working with objects. Just define a class with public properties to
represent database fields and static variables to describe the database relationship.

    class User {
        // Class properties
        public $id;
        public $name;
        public $email;

        // Class configuration
        static $table = 'user';
        static $id_field = 'id';
        static $name_field = 'name';
    }

### Class Configuration

* The `table` property represents the database table. This property is required. 
* The `id_field` property represents the auto-incrementing identity field in the table. This property is required for saving and deleting records. 
* The `name_field` property is used for finding records by name. This property is optional.

### Loading Objects

To define the object use the `using` function and pass in the class name.

    $db->using('User');

After setting your object, you can then use the `find` method to populate the object. If you pass in an int
Sparrow will search using the id field.

    $user = $db->find(123);

This will execute:

    SELECT * FROM user WHERE id = 123

If you pass in a string Sparrow will search using the name field.

    $user = $db->find('Bob');

This will execute:

    SELECT * FROM user WHERE name = 'Bob';

If you pass in an array Sparrow will use the fields specified in the array.

    $user = $db->find(
        array('email' => 'bob@aol.com')
    );

This will execute:

    SELECT * FROM user WHERE email = 'bob@aol.com'

If the `find` method retrieves multiple records, it will return an array of objects
instead of a single object.

### Saving Objects

To save an object, just populate your object properties and use the `save` function.

    $user = new User();
    $user->name = 'Bob';
    $user->email = 'bob@aol.com';

    $db->save($user);

This will execute:

    INSERT INTO user (name, email) VALUES ('Bob', 'bob@aol.com')

To update an object, use the `save` function with the `id_field` property populated.

    $user = new User();
    $user->id = 123;
    $user->name = 'Bob';
    $user->email = 'bob@aol.com';

    $db->save($user);

This will execute:

    UPDATE user SET name = 'Bob', email = 'bob@aol.com' WHERE id = 123

To update an existing record, just fetch an object from the database, update its properties, then save it.

    // Fetch an object from the database
    $user = $db->find(123);

    // Update the object
    $user->name = 'Fred';

    // Update the database
    $db->save($user);

By default, all of the object's properties will be included in the update. To specify only specific fields, pass in
an additional array of fields to the `save` function.

    $db->save($user, array('email'));

This will execute:

    UPDATE user SET email = 'bob@aol.com' WHERE id = 123

### Deleting Objects

To delete an object, use the `remove` function.

    $user = $db->find(123);

    $db->remove($user);

### Advanced Finding

You can use the sql builder functions to further define criteria for loading objects.

    $db->using('User')
        ->where('id >', 10)
        ->sortAsc('name')
        ->find();

This will execute:

    SELECT * FROM user WHERE id > 10 ORDER BY name ASC

You can also pass in raw SQL to load your objects.

    $db->using('User')
        ->sql('SELECT * FROM user WHERE id > 10')
        ->find();

## Statistics

Sparrow has built in query statistics tracking. To enable it, just set the `stats_enabled` property.

    $db->stats_enabled = true;

After running your queries, get the stats array:

    $stats = $db->getStats();

The stats array contains the total time for all queries and an array of all queries executed
with individual query times.

    array(6) {
      ["queries"]=>
      array(2) {
        [0]=>
        array(4) {
          ["query"]=>
              string(38) "SELECT * FROM user WHERE uid=1"
          ["time"]=>
              float(0.00016617774963379)
          ["rows"]=>
              int(1)
          ["changes"]=>
              int(0)
        }
        [1]=>
        array(4) {
          ["query"]=>
              string(39) "SELECT * FROM user WHERE uid=10"
          ["time"]=>
              float(0.00026392936706543)
          ["rows"]=>
              int(0)
          ["changes"]=>
              int(0)
        }
      }
      ["total_time"]=>
          float(0.00043010711669922)
      ["num_queries"]=>
          int(2)
      ["num_rows"]=>
          int(2)
      ["num_changes"]=>
          int(0)
      ["avg_query_time"]=>
          float(0.00021505355834961)
    }

## Debugging

When Sparrow encounters an error while executing a query, it will raise an exception with the database
error message. If you want to display the generated SQL along with the error message, set the `show_sql` property.

    $db->show_sql = true;

## Requirements

Sparrow requires PHP 5.1 or greater.

## License

Sparrow is released under the [MIT](https://github.com/mikecao/sparrow/blob/master/LICENSE) license.
