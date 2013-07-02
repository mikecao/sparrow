<?php
/**
 * Sparrow: A simple database toolkit.
 *
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license   MIT, http://www.opensource.org/licenses/mit-license.php
 */
class Sparrow {
    protected $table;
    protected $where;
    protected $joins;
    protected $order;
    protected $groups;
    protected $having;
    protected $distinct;
    protected $limit;
    protected $offset;
    protected $sql;

    protected $db;
    protected $db_type;
    protected $cache;
    protected $cache_type;
    protected $stats;
    protected $query_time;
    protected $class;

    protected static $db_types = array(
        'pdo', 'mysqli', 'mysql', 'pgsql', 'sqlite', 'sqlite3'
    );
    protected static $cache_types = array(
        'memcached', 'memcache', 'xcache'
    );

    public $last_query;
    public $num_rows;
    public $insert_id;
    public $affected_rows;
    public $is_cached = false;
    public $stats_enabled = false;
    public $show_sql = false;
    public $key_prefix = '';

    /**
     * Class constructor.
     */
    public function __construct() {
    }

    /*** Core Methods ***/

    /**
     * Joins string tokens into a SQL statement.
     *
     * @param string $sql SQL statement
     * @param string $input Input string to append
     * @return string New SQL statement
     */
    public function build($sql, $input) {
        return (strlen($input) > 0) ? ($sql.' '.$input) : $sql;
    }

    /**
     * Parses a connection string into an object.
     *
     * @param string $connection Connection string
     * @return array Connection information
     * @throws Exception For invalid connection string
     */
    public function parseConnection($connection) {
        $url = parse_url($connection);

        if (empty($url)) {
            throw new Exception('Invalid connection string.');
        }

        $cfg = array();

        $cfg['type'] = isset($url['scheme']) ? $url['scheme'] : $url['path'];
        $cfg['hostname'] = isset($url['host']) ? $url['host'] : null;
        $cfg['database'] = isset($url['path']) ? substr($url['path'],1) : null;
        $cfg['username'] = isset($url['user']) ? $url['user'] : null;
        $cfg['password'] = isset($url['pass']) ? $url['pass'] : null;
        $cfg['port'] = isset($url['port']) ? $url['port'] : null;

        return $cfg;
    }

    /**
     * Gets the query statistics.
     */
    public function getStats() {
        $this->stats['total_time'] = 0;
        $this->stats['num_queries'] = 0;
        $this->stats['num_rows'] = 0;
        $this->stats['num_changes'] = 0;

        if (isset($this->stats['queries'])) {
            foreach ($this->stats['queries'] as $query) {
                $this->stats['total_time'] += $query['time'];
                $this->stats['num_queries'] += 1;
                $this->stats['num_rows'] += $query['rows'];
                $this->stats['num_changes'] += $query['changes'];
            }
        }

        $this->stats['avg_query_time'] =
            $this->stats['total_time'] /
            (float)(($this->stats['num_queries'] > 0) ? $this->stats['num_queries'] : 1);

        return $this->stats;
    }

    /**
     * Checks whether the table property has been set.
     */
    public function checkTable() {
        if (!$this->table) {
            throw new Exception('Table is not defined.');
        }
    }

    /**
     * Checks whether the class property has been set.
     */
    public function checkClass() {
        if (!$this->class) {
            throw new Exception('Class is not defined.');
        }
    }

    /**
     * Resets class properties.
     */
    public function reset() {
        $this->where = '';
        $this->joins = '';
        $this->order = '';
        $this->groups = '';
        $this->having = '';
        $this->distinct = '';
        $this->limit = '';
        $this->offset = '';
        $this->sql = '';
    }

    /*** SQL Builder Methods ***/

    /**
     * Parses a condition statement.
     *
     * @param string $field Database field
     * @param string $value Condition value
     * @param string $join Joining word
     * @param boolean $escape Escape values setting
     * @return string Condition as a string
     * @throws Exception For invalid where condition
     */
    protected function parseCondition($field, $value = null, $join = '', $escape = true) {
        if (is_string($field)) {
            if ($value === null) return $join.' '.trim($field);

            $operator = '';

            if (strpos($field, ' ') !== false) {
                list($field, $operator) = explode(' ', $field);
            }

            if (!empty($operator)) {
                switch ($operator) {
                    case '%':
                        $condition = ' LIKE ';
                        break;

                    case '!%':
                        $condition = ' NOT LIKE ';
                        break;

                    case '@':
                        $condition = ' IN ';
                        break;

                    case '!@':
                        $condition = ' NOT IN ';
                        break;

                    default:
                        $condition = $operator;
                }
            }
            else {
                $condition = '=';
            }

            if (empty($join)) { 
                $join = ($field{0} == '|') ? ' OR' : ' AND';
            }

            if (is_array($value)) {
                if (strpos($operator, '@') === false) $condition = ' IN ';
                $value = '('.implode(',', array_map(array($this, 'quote'), $value)).')';
            }
            else {
                $value = ($escape && !is_numeric($value)) ? $this->quote($value) : $value;
            }

            return $join.' '.str_replace('|', '', $field).$condition.$value;
        }
        else if (is_array($field)) {
            $str = '';
            foreach ($field as $key => $value) {
                $str .= $this->parseCondition($key, $value, $join, $escape);
                $join = '';
            }
            return $str;
        }
        else {
            throw new Exception('Invalid where condition.');
        }
    }

    /**
     * Sets the table.
     *
     * @param string $table Table name
     * @param boolean $reset Reset class properties
     * @return object Self reference
     */
    public function from($table, $reset = true) {
        $this->table = $table;
        if ($reset) {
            $this->reset();
        }

        return $this;
    }

    /**
     * Adds a table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     * @param string $type Type of join
     * @return object Self reference
     * @throws Exception For invalid join type
     */
    public function join($table, array $fields, $type = 'INNER') {
        static $joins = array(
            'INNER',
            'LEFT OUTER',
            'RIGHT OUTER',
            'FULL OUTER'
        );

        if (!in_array($type, $joins)) {
            throw new Exception('Invalid join type.');
        }

        $this->joins .= ' '.$type.' JOIN '.$table.
            $this->parseCondition($fields, null, ' ON', false);

        return $this;
    }

    /**
     * Adds a left table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     * @return object Self reference
     */
    public function leftJoin($table, array $fields) {
        return $this->join($table, $fields, 'LEFT OUTER');
    }

    /**
     * Adds a right table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     * @return object Self reference
     */
    public function rightJoin($table, array $fields) {
        return $this->join($table, $fields, 'RIGHT OUTER');
    }

    /**
     * Adds a full table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     * @return object Self reference
     */
    public function fullJoin($table, array $fields) {
        return $this->join($table, $fields, 'FULL OUTER');
    }

    /**
     * Adds where conditions.
     *
     * @param string|array $field A field name or an array of fields and values.
     * @param string $value A field value to compare to
     * @return object Self reference
     */
    public function where($field, $value = null) {
        $join = (empty($this->where)) ? 'WHERE' : '';
        $this->where .= $this->parseCondition($field, $value, $join);

        return $this;
    }

    /**
     * Adds an ascending sort for a field.
     *
     * @param string $field Field name
     * @return object Self reference
     */ 
    public function sortAsc($field) {
        return $this->orderBy($field, 'ASC');
    }

    /**
     * Adds an descending sort for a field.
     *
     * @param string $field Field name
     * @return object Self reference
     */ 
    public function sortDesc($field) {
        return $this->orderBy($field, 'DESC');        
    }

    /**
     * Adds fields to order by.
     *
     * @param string $field Field name
     * @param string $direction Sort direction
     * @return object Self reference
     */
    public function orderBy($field, $direction = 'ASC') {
        $join = (empty($this->order)) ? 'ORDER BY' : ',';

        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $field[$key] = $value.' '.$direction;
            }
        }
        else {
            $field .= ' '.$direction;
        }

        $fields = (is_array($field)) ? implode(', ', $field) : $field;

        $this->order .= $join.' '.$fields;

        return $this;
    }

    /**
     * Adds fields to group by.
     *
     * @param string|array $field Field name or array of field names
     * @return object Self reference
     */
    public function groupBy($field) {
        $join = (empty($this->order)) ? 'GROUP BY' : ',';
        $fields = (is_array($field)) ? implode(',', $field) : $field;

        $this->groups .= $join.' '.$fields;

        return $this;
    }

    /**
     * Adds having conditions.
     *
     * @param string|array $field A field name or an array of fields and values.
     * @param string $value A field value to compare to
     * @return object Self reference
     */
    public function having($field, $value = null) {
        $join = (empty($this->having)) ? 'HAVING' : '';
        $this->having .= $this->parseCondition($field, $value, $join);

        return $this;
    }

    /**
     * Adds a limit to the query.
     *
     * @param int $limit Number of rows to limit
     * @param int $offset Number of rows to offset
     * @return object Self reference
     */
    public function limit($limit, $offset = null) {
        if ($limit !== null) {
            $this->limit = 'LIMIT '.$limit;
        }
        if ($offset !== null) {
            $this->offset($offset);
        }

        return $this;
    }

    /**
     * Adds an offset to the query.
     *
     * @param int $offset Number of rows to offset
     * @param int $limit Number of rows to limit
     * @return object Self reference
     */
    public function offset($offset, $limit = null) {
        if ($offset !== null) {
            $this->offset = 'OFFSET '.$offset;
        }
        if ($limit !== null) {
            $this->limit($limit);
        }

        return $this;
    }

    /**
     * Sets the distinct keyword for a query.
     */
    public function distinct($value = true) {
        $this->distinct = ($value) ? 'DISTINCT' : '';

        return $this;
    }

    /**
     * Sets a between where clause.
     *
     * @param string $field Database field
     * @param string $value1 First value
     * @param string $value2 Second value
     */
    public function between($field, $value1, $value2) {
        $this->where(sprintf(
            '%s BETWEEN %s AND %s',
            $field,
            $this->quote($value1),
            $this->quote($value2)
        ));
    }

    /**
     * Builds a select query.
     *
     * @param array|string $fields Array of field names to select
     * @param int $limit Limit condition
     * @param int $offset Offset condition
     * @return object Self reference
     */
    public function select($fields = '*', $limit = null, $offset = null) {
        $this->checkTable();

        $fields = (is_array($fields)) ? implode(',', $fields) : $fields;
        $this->limit($limit, $offset);

        $this->sql(array(
            'SELECT',
            $this->distinct,
            $fields,
            'FROM',
            $this->table,
            $this->joins,
            $this->where,
            $this->groups,
            $this->having,
            $this->order,
            $this->limit,
            $this->offset
        ));

        return $this;
    }

    /**
     * Builds an insert query.
     *
     * @param array $data Array of key and values to insert
     * @return object Self reference
     */
    public function insert(array $data) {
        $this->checkTable();

        if (empty($data)) return $this;

        $keys = implode(',', array_keys($data));
        $values = implode(',', array_values(
            array_map(
                array($this, 'quote'),
                $data
            )
        ));

        $this->sql(array(
            'INSERT INTO',
            $this->table,
            '('.$keys.')',
            'VALUES',
            '('.$values.')'
        ));

        return $this;
    }

    /**
     * Builds an update query.
     *
     * @param string|array $data Array of keys and values, or string literal
     * @return object Self reference
     */
    public function update($data) {
        $this->checkTable();

        if (empty($data)) return $this;

        $values = array();

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $values[] = (is_numeric($key)) ? $value : $key.'='.$this->quote($value);
            }
        }
        else {
            $values[] = (string)$data;
        }

        $this->sql(array(
            'UPDATE',
            $this->table,
            'SET',
            implode(',', $values),
            $this->where
        ));

        return $this;
    }

    /**
     * Builds a delete query.
     *
     * @param array $where Where conditions
     * @return object Self reference
     */
    public function delete($where = null) {
        $this->checkTable();

        if ($where !== null) {
            $this->where($where);
        }

        $this->sql(array(
            'DELETE FROM',
            $this->table,
            $this->where
        ));

        return $this;
    }

    /**
     * Gets or sets the SQL statement.
     *
     * @param string|array SQL statement
     * @return string SQL statement
     */
    public function sql($sql = null) {
        if ($sql !== null) {
            $this->sql = trim(
                (is_array($sql)) ?
                    array_reduce($sql, array($this, 'build')) :
                    $sql
            );

            return $this;
        }

        return $this->sql;
    }

    /*** Database Access Methods ***/

    /**
     * Sets the database connection.
     *
     * @param string|array|object $db Database connection string, array or object
     * @throws Exception For connection error
     */
    public function setDb($db) {
        $this->db = null;

        // Connection string
        if (is_string($db)) {
            $this->setDb($this->parseConnection($db));
        }
        // Connection information
        else if (is_array($db)) {
            switch ($db['type']) {
                case 'mysqli':
                    $this->db = new mysqli(
                        $db['hostname'],
                        $db['username'],
                        $db['password'],
                        $db['database']
                    );

                    if ($this->db->connect_error) {
                        throw new Exception('Connection error: '.$this->db->connect_error);
                    }

                    break;

                case 'mysql':
                    $this->db = mysql_connect(
                        $db['hostname'],
                        $db['username'],
                        $db['password']
                    );

                    if (!$this->db) {
                        throw new Exception('Connection error: '.mysql_error());
                    }

                    mysql_select_db($db['database'], $this->db);

                    break;

                case 'pgsql':
                    $str = sprintf(
                        'host=%s dbname=%s user=%s password=%s',
                        $db['hostname'],
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );

                    $this->db = pg_connect($str);

                    break;

                case 'sqlite':
                    $this->db = sqlite_open($db['database'], 0666, $error);

                    if (!$this->db) {
                        throw new Exception('Connection error: '.$error);
                    }

                    break;

                case 'sqlite3':
                    $this->db = new SQLite3($db['database']);

                    break;

                case 'pdomysql':
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 3306,
                        $db['database']
                    );

                    $this->db = new PDO($dsn, $db['username'], $db['password']);
                    $db['type'] = 'pdo';

                    break;

                case 'pdopgsql':
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 5432,
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );

                    $this->db = new PDO($dsn);
                    $db['type'] = 'pdo';

                    break;

                case 'pdosqlite':
                    $this->db = new PDO('sqlite:/'.$db['database']);
                    $db['type'] = 'pdo';

                    break; 
            }

            if ($this->db == null) {
                throw new Exception('Undefined database.');
            }

            $this->db_type = $db['type'];
        }
        // Connection object or resource
        else {
            $type = $this->getDbType($db);

            if (!in_array($type, self::$db_types)) {
                throw new Exception('Invalid database type.');
            }

            $this->db = $db;
            $this->db_type = $type;
        }
    }

    /**
     * Gets the database connection.
     *
     * @return object Database connection
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Gets the database type.
     * 
     * @param object|resource $db Database object or resource
     * @return string Database type
     */
    public function getDbType($db) {
        if (is_object($db)) {
            return strtolower(get_class($db));
        }
        else if (is_resource($db)) {
            switch (get_resource_type($db)) {
                case 'mysql link':
                    return 'mysql';

                case 'sqlite database':
                    return 'sqlite';

                case 'pgsql link':
                    return 'pgsql';
            }
        }

        return null;
    }

    /**
     * Executes a sql statement.
     *
     * @param string $key Cache key
     * @param int $expire Expiration time in seconds
     * @return object Query results object
     * @throws Exception When database is not defined
     */
    public function execute($key = null, $expire = 0) {
        if (!$this->db) {
            throw new Exception('Database is not defined.');
        }

        if ($key !== null) {
            $result = $this->fetch($key);

            if ($this->is_cached) {
                return $result;
            }
        }

        $result = null;

        $this->is_cached = false;
        $this->num_rows = 0;
        $this->affected_rows = 0;
        $this->insert_id = -1;
        $this->last_query = $this->sql;

        if ($this->stats_enabled) {
            if (empty($this->stats)) {
                $this->stats = array(
                    'queries' => array()
                );
            }

            $this->query_time = microtime(true);
        }

        if (!empty($this->sql)) {
            $error = null;

            switch ($this->db_type) {
                case 'pdo':
                    try {
                        $result = $this->db->prepare($this->sql);

                        if (!$result) {
                            $error = $this->db->errorInfo();
                        }
                        else {
                            $result->execute();

                            $this->num_rows = $result->rowCount();
                            $this->affected_rows = $result->rowCount();
                            $this->insert_id = $this->db->lastInsertId();
                        }
                    }
                    catch (PDOException $ex) {
                        $error = $ex->getMessage();
                    }

                    break;

                case 'mysqli':
                    $result = $this->db->query($this->sql);

                    if (!$result) {
                        $error = $this->db->error;
                    }
                    else {
                        if (is_object($result)) {
                            $this->num_rows = $result->num_rows;
                        }
                        else {
                            $this->affected_rows = $this->db->affected_rows;
                        }
                        $this->insert_id = $this->db->insert_id;
                    }

                    break;

                case 'mysql':
                    $result = mysql_query($this->sql, $this->db);

                    if (!$result) {
                        $error = mysql_error();
                    }
                    else {
                        if (!is_bool($result)) {
                            $this->num_rows = mysql_num_rows($result);
                        }
                        else {
                            $this->affected_rows = mysql_affected_rows($this->db);
                        }
                        $this->insert_id = mysql_insert_id($this->db);
                    }

                    break;

                case 'pgsql':
                    $result = pg_query($this->db, $this->sql);

                    if (!$result) {
                       $error = pg_last_error($this->db);
                    }
                    else {
                        $this->num_rows = pg_num_rows($result);
                        $this->affected_rows = pg_affected_rows($result);
                        $this->insert_id = pg_last_oid($result);
                    }

                    break;

                case 'sqlite':
                    $result = sqlite_query($this->db, $this->sql, SQLITE_ASSOC, $error);

                    if ($result !== false) {
                        $this->num_rows = sqlite_num_rows($result);
                        $this->affected_rows = sqlite_changes($this->db);
                        $this->insert_id = sqlite_last_insert_rowid($this->db);
                    }

                    break;

                case 'sqlite3':
                    $result = $this->db->query($this->sql);

                    if ($result === false) {
                        $error = $this->db->lastErrorMsg();
                    }
                    else {
                        $this->num_rows = 0;
                        $this->affected_rows = ($result) ? $this->db->changes() : 0;
                        $this->insert_id = $this->db->lastInsertRowId();
                    }

                    break;
            }

            if ($error !== null) {
                if ($this->show_sql) {
                    $error .= "\nSQL: ".$this->sql;
                }
                throw new Exception('Database error: '.$error);
            }
        }

        if ($this->stats_enabled) {
            $time = microtime(true) - $this->query_time;
            $this->stats['queries'][] = array(
                'query' => $this->sql,
                'time' => $time,
                'rows' => (int)$this->num_rows,
                'changes' => (int)$this->affected_rows
            );
        }

        return $result;
    }

    /**
     * Fetch multiple rows from a select query.
     *
     * @param string $key Cache key
     * @param int $expire Expiration time in seconds
     * @return array Rows
     */
    public function many($key = null, $expire = 0) {
        if (empty($this->sql)) {
            $this->select();
        }

        $data = array();

        $result = $this->execute($key, $expire);

        if ($this->is_cached) {
            $data = $result;

            if ($this->stats_enabled) {
                $this->stats['cached'][$this->key_prefix.$key] = $this->sql;
            }
        }
        else {
            switch ($this->db_type) {
                case 'pdo':
                    $data = $result->fetchAll(PDO::FETCH_ASSOC);
                    $this->num_rows = sizeof($data);
 
                    break;

                case 'mysqli':
                    if (function_exists('mysqli_fetch_all')) {
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    else {
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                    }
                    $result->close();
                    break;
           
                case 'mysql':
                    while ($row = mysql_fetch_assoc($result)) {
                        $data[] = $row;
                    }
                    mysql_free_result($result);
                    break;

                case 'pgsql':
                    $data = pg_fetch_all($result);
                    pg_free_result($result);
                    break;

                case 'sqlite':
                    $data = sqlite_fetch_all($result, SQLITE_ASSOC);
                    break;

                case 'sqlite3':
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $data[] = $row;
                        }
                        $result->finalize();
                        $this->num_rows = sizeof($data);
                    }
                    break;
            }
        }

        if (!$this->is_cached && $key !== null) {
            $this->store($key, $data, $expire);
        }

        return $data;
    }

    /**
     * Fetch a single row from a select query.
     *
     * @param string $key Cache key
     * @param int $expire Expiration time in seconds
     * @return array Row
     */
    public function one($key = null, $expire = 0) {
        if (empty($this->sql)) {
            $this->limit(1)->select();
        }

        $data = $this->many($key, $expire);

        $row = (!empty($data)) ? $data[0] : array();

        return $row;
    }

    /**
     * Fetch a value from a field.
     *
     * @param string $name Database field name
     * @param string $key Cache key
     * @param int $expire Expiration time in seconds
     * @return mixed Row value
     */
    public function value($name, $key = null, $expire = 0) {
        $row = $this->one($key, $expire);

        $value = (!empty($row)) ? $row[$name] : null;

        return $value;
    }

    /**
     * Gets the min value for a specified field.
     *
     * @param string $field Field name
     * @param int $expire Expiration time in seconds
     * @param string $key Cache key
     * @return object Self reference
     */
    public function min($field, $key = null, $expire = 0) {
        $this->select('MIN('.$field.') min_value');

        return $this->value(
            'min_value',
            $key,
            $expire
        );
    }

    /**
     * Gets the max value for a specified field.
     *
     * @param string $field Field name
     * @param int $expire Expiration time in seconds
     * @param string $key Cache key
     * @return object Self reference
     */
    public function max($field, $key = null, $expire = 0) {
        $this->select('MAX('.$field.') max_value');

        return $this->value(
            'max_value',
            $key,
            $expire
        );
    }

    /**
     * Gets the sum value for a specified field.
     *
     * @param string $field Field name
     * @param int $expire Expiration time in seconds
     * @param string $key Cache key
     * @return object Self reference
     */
    public function sum($field, $key = null, $expire = 0) {
        $this->select('SUM('.$field.') sum_value');

        return $this->value(
            'sum_value',
            $key,
            $expire
        );
    }

    /**
     * Gets the average value for a specified field.
     *
     * @param string $field Field name
     * @param int $expire Expiration time in seconds
     * @param string $key Cache key
     * @return object Self reference
     */
    public function avg($field, $key = null, $expire = 0) {
        $this->select('AVG('.$field.') avg_value');

        return $this->value(
            'avg_value',
            $key,
            $expire
        ); 
    }

    /**
     * Gets a count of records for a table.
     *
     * @param string $field Field name
     * @param string $key Cache key
     * @param int $expire Expiration time in seconds
     * @return object Self reference
     */
    public function count($field = '*', $key = null, $expire = 0) {
        $this->select('COUNT('.$field.') num_rows');

        return $this->value(
            'num_rows',
            $key,
            $expire
        );
    }

    /**
     * Wraps quotes around a string and escapes the content for a string parameter.
     *
     * @param mixed $value mixed value
     * @return mixed Quoted value
     */
    public function quote($value) {
        if ($value === null) return 'NULL';

        if (is_string($value)) {
            if ($this->db !== null) {
                switch ($this->db_type) {
                    case 'pdo':
                        return $this->db->quote($value);

                    case 'mysqli':
                        return "'".$this->db->real_escape_string($value)."'";

                    case 'mysql':
                        return "'".mysql_real_escape_string($value, $this->db)."'";

                    case 'pgsql':
                        return "'".pg_escape_string($this->db, $value)."'";

                    case 'sqlite':
                        return "'".sqlite_escape_string($value)."'";

                    case 'sqlite3':
                        return "'".$this->db->escapeString($value)."'";
                }
            }

            $value = str_replace(
                array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
                $value
            );

            return "'$value'";
        }

        return $value;
    }

    /*** Cache Methods ***/

    /**
     * Sets the cache connection.
     *
     * @param string|object $cache Cache connection string or object
     * @throws Exception For invalid cache type
     */
    public function setCache($cache) {
        $this->cache = null;

        // Connection string
        if (is_string($cache)) {
            if ($cache{0} == '.' || $cache{0} == '/') {
                $this->cache = $cache;
                $this->cache_type = 'file';
            }
            else {
                $this->setCache($this->parseConnection($cache));
            }
        }
        // Connection information
        else if (is_array($cache)) {
            switch ($cache['type']) {
                case 'memcache':
                    $this->cache = new Memcache;
                    $this->cache->connect(
                        $cache['hostname'],
                        $cache['port']
                    );
                    break;

                case 'memcached':
                    $this->cache = new Memcached;
                    $this->cache->addServer(
                        $cache['hostname'],
                        $cache['port']
                    );
                    break;

                default:
                    $this->cache = $cache['type'];
            }

            $this->cache_type = $cache['type'];
        }
        // Cache object
        else if (is_object($cache)) {
            $type = strtolower(get_class($cache));

            if (!in_array($type, self::$cache_types)) {
                throw new Exception('Invalid cache type.');
            }

            $this->cache = $cache;
            $this->cache_type = $type;
        }
    }

    /**
     * Gets the cache instance.
     *
     * @return object Cache instance
     */
    public function getCache() {
        return $this->cache;
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $expire Expiration time in seconds
     */
    public function store($key, $value, $expire = 0) {
        $key = $this->key_prefix.$key;

        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->set($key, $value, $expire);
                break;

            case 'memcache':
                $this->cache->set($key, $value, 0, $expire);
                break;

            case 'apc':
                apc_store($key, $value, $expire);
                break;

            case 'xcache':
                xcache_set($key, $value, $expire);
                break;

            case 'file':
                $file = $this->cache.'/'.md5($key);
                $data = array(
                    'value' => $value,
                    'expire' => ($expire > 0) ? (time() + $expire) : 0
                );
                file_put_contents($file, serialize($data));
                break;

            default:
                $this->cache[$key] = $value;
        }
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key Cache key
     * @return mixed Cached value
     */
    public function fetch($key) {
        $key = $this->key_prefix.$key;

        switch ($this->cache_type) {
            case 'memcached':
                $value = $this->cache->get($key);
                $this->is_cached = ($this->cache->getResultCode() == Memcached::RES_SUCCESS);
                return $value;

            case 'memcache':
                $value = $this->cache->get($key);
                $this->is_cached = ($value !== false);
                return $value;

            case 'apc':
                return apc_fetch($key, $this->is_cached);

            case 'xcache':
                $this->is_cached = xcache_isset($key);
                return xcache_get($key);

            case 'file':
                $file = $this->cache.'/'.md5($key);

                if ($this->is_cached = file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    if ($data['expire'] == 0) {
                        return $data['value'];
                    }
                    else if (time() > $data['expire']) {
                        $this->is_cached = false;
                    }
                }
                break;

            default:
                return $this->cache[$key];
        }
        return null;
    }

    /**
     * Clear a value from the cache.
     *
     * @param string $key Cache key
     * @return object Self reference
     */
    public function clear($key) {
        $key = $this->key_prefix.$key;

        switch ($this->cache_type) {
            case 'memcached':
                return $this->cache->delete($key);

            case 'memcache':
                return $this->cache->delete($key);

            case 'apc':
                return apc_delete($key);

            case 'xcache':
                return xcache_unset($key);

            case 'file':
                $file = $this->cache.'/'.$key;
                if (file_exists($file)) {
                    return unlink($file);
                }
                return false;

            default:
                if (isset($this->cache[$key])) {
                    unset($this->cache[$key]);
                    return true;
                }
                return false;
        }
    }

    /**
     * Flushes out the cache.
     */
    public function flush() {
        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->flush();
                break;

            case 'memcache':
                $this->cache->flush();
                break;

            case 'apc':
                apc_clear_cache();
                break;

            case 'xcache':
                xcache_clear_cache();
                break;

            case 'file':
                if ($handle = opendir($this->cache)) {
                    while (false !== ($file = readdir($handle))) {
                        if ($file != '.' && $file != '..') {
                            unlink($this->cache.'/'.$file);
                        }
                    }
                    closedir($handle);
                }
                break;

            default:
                $this->cache = array();
                break;
        }
    }

    /*** Object Methods ***/

    /**
     * Sets the class.
     *
     * @param string|object $class Class name or instance
     * @return object Self reference
     */
    public function using($class) {
        if (is_string($class)) {
            $this->class = $class;
        }
        else if (is_object($class)) {
            $this->class = get_class($class);
        }

        $this->reset();

        return $this;
    }

    /**
     * Loads properties for an object.
     *
     * @param object $object Class instance
     * @param array $data Property data
     * @return object Populated object
     */
    public function load($object, array $data) {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }

        return $object;
    }
   
    /**
     * Finds and populates an object.
     *
     * @param int|string|array Search value
     * @param string $key Cache key
     * @return object Populated object
     */
    public function find($value = null, $key = null) {
        $this->checkClass();

        $properties = $this->getProperties();

        $this->from($properties->table, false);

        if ($value !== null) {
            if (is_int($value) && property_exists($properties, 'id_field')) {
                $this->where($properties->id_field, $value);
            }
            else if (is_string($value) && property_exists($properties, 'name_field')) {
                $this->where($properties->name_field, $value);
            }
            else if (is_array($value)) {
                $this->where($value);
            }
        }

        if (empty($this->sql)) {
            $this->select();
        }

        $data = $this->many($key);
        $objects = array();

        foreach ($data as $row) {
            $objects[] = $this->load(new $this->class, $row);
        }

        return (sizeof($objects) == 1) ? $objects[0] : $objects;
    }

    /**
     * Saves an object to the database.
     *
     * @param object $object Class instance
     * @param array $fields Select database fields to save
     */
    public function save($object, array $fields = null) {
        $this->using($object);

        $properties = $this->getProperties();

        $this->from($properties->table);

        $data = get_object_vars($object);
        $id = $object->{$properties->id_field};

        unset($data[$properties->id_field]);

        if ($id === null) {
            $this->insert($data)
                ->execute();

            $object->{$properties->id_field} = $this->insert_id;
        }
        else {
            if ($fields !== null) {
                $keys = array_flip($fields);
                $data = array_intersect_key($data, $keys);
            }

            $this->where($properties->id_field, $id)
                ->update($data)
                ->execute();
        }

        return $this->class;
    }

    /**
     * Removes an object from the database.
     *
     * @param object $object Class instance
     */
    public function remove($object) {
        $this->using($object);

        $properties = $this->getProperties();

        $this->from($properties->table);

        $id = $object->{$properties->id_field};

        if ($id !== null) {
            $this->where($properties->id_field, $id)
                ->delete()
                ->execute();
        }
    }

    /**
     * Gets class properties.
     *
     * @return object Class properties
     */
    public function getProperties() {
        static $properties = array();

        if (!$this->class) return array();

        if (!isset($properties[$this->class])) {
            static $defaults = array(
                'table' => null,
                'id_field' => null,
                'name_field' => null
            );

            $reflection = new ReflectionClass($this->class);
            $config = $reflection->getStaticProperties();

            $properties[$this->class] = (object)array_merge($defaults, $config);
        }

        return $properties[$this->class];
    }
}
