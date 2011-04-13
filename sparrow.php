<?php
/**
 * Sparrow: A simple SQL engine.
 *
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license http://www.opensource.org/licenses/mit-license.php
 * @version 0.1
 */
class Sparrow {
    protected $table;
    protected $where;
    protected $joins;
    protected $order;
    protected $group;
    protected $having;
    protected $distinct;
    protected $limit;
    protected $offset;
    protected $sql;

    protected $db;
    protected $stats;
    protected $query_time;

    public $last_query;
    public $num_rows;
    public $insert_id;
    public $affected_rows;
    public $stats_enabled = false;

    /**
     * Class constructor.
     *
     * @param string $db Database connection string
     */
    public function __construct($connection = null) {
        if ($connection !== null) {
            $this->db = $this->parseConnection($connection);
        }
    }

    /**
     * Sets the table.
     *
     * @param string $table Table name
     */
    public function using($table) {
        $this->table = $table;
        $this->where = array();
        $this->joins = array();
        $this->order = array();
        $this->group = array();
        $this->having = array();
        $this->distinct = null;
        $this->limit = null;
        $this->offset = null;
        $this->sql = null;

        return $this;
    }

    /**
     * Adds a table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     * @param string $type Type of join
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

        $this->parseCondition(
            $this->joins[$type.' JOIN '.$table],
            $fields,
            null,
            false
        );

        return $this;
    }

    /**
     * Adds a left table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     */
    public function leftJoin($table, array $fields) {
        return $this->join($table, $fields, 'LEFT OUTER');
    }

    /**
     * Adds a right table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     */
    public function rightJoin($table, array $fields) {
        return $this->join($table, $fields, 'RIGHT OUTER');
    }

    /**
     * Adds a full table join.
     *
     * @param string $table Table to join to
     * @param array $fields Fields to join on
     */
    public function fullJoin($table, array $fields) {
        return $this->join($table, $fields, 'FULL OUTER');
    }

    /**
     * Adds where conditions.
     *
     * @param string|array $field A field name or an array of fields and values.
     * @param string $value A field value to compare to
     */
    public function where($fields, $value = null) {
        $this->parseCondition($this->where, $fields, $value);

        return $this;
    }

    /**
     * Adds an ascending sort for a field.
     *
     * @param string $field Field name
     */ 
    public function sortAsc($field) {
        $this->order[] = $field.' ASC';

        return $this;
    }

    /**
     * Adds an descending sort for a field.
     *
     * @param string $field Field name
     */ 
    public function sortDesc($field) {
        $this->order[] = $field.' ASC';

        return $this;
    }

    /**
     * Adds fields to group by.
     *
     * @param string|array $field Field name or array of field names
     */
    public function groupBy($field) {
        if (is_array($field)) {
            foreach ($field as $value) {
                $this->group[] = $value;
            }
        }
        else {
            $this->group[] = $field;
        }

        return $this;
    }

    /**
     * Adds having conditions.
     *
     * @param string|array $field A field name or an array of fields and values.
     * @param string $value A field value to compare to
     */
    public function having($field, $value = null) {
        $this->parseCondition($this->having, $field, $value);

        return $this;
    }

    /**
     * Adds a limit to the query.
     *
     * @param int $limit Number of rows to limit
     * @param int $offset Number of rows to offset
     */
    public function limit($limit, $offset = null) {
        $this->limit = $limit;
        if ($offset !== null) $this->offset = $offset;

        return $this;
    }

    /**
     * Adds an offset to the query.
     *
     * @param int $offset Number of rows to offset
     * @param int $limit Number of rows to limit
     */
    public function offset($offset, $limit = null) {
        $this->offset = $offset;
        if ($limit !== null) $this->limit = $limit;

        return $this;
    }

    /**
     * Sets the distinct keywork for a query.
     */
    public function distinct($value = true) {
        $this->distinct = ($value) ? ' DISTINCT ' : '';

        return $this;
    }

    /**
     * Builds a select query.
     *
     * @param array $fields Array of field names to select
     * @return string SQL statement
     */
    public function select($fields = null, $limit = null, $offset = null) {
        if (empty($this->table)) return $this;

        $sql = 'SELECT '.
            $this->parseFields($fields, '*').
            ' FROM '.
            $this->table;

        if (!empty($this->joins)) {
            foreach ($this->joins as $key => $value) {
                $sql .= ' '.$key.' ON '.implode('', $value);
            }
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE '.implode('', $this->where);
        } 

        if (!empty($this->order)) {
            $sql .= ' ORDER BY '.implode(',', $this->order);
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING '.implode('', $this->having);
        }

        if ($limit !== null || $this->limit !== null) {
            $sql .= ' LIMIT '.(($limit !== null) ? $limit : $this->limit);
        }

        if ($offset !== null || $this->offset !== null) {
            $sql .= ' OFFSET '.(($offset !== null) ? $offset : $this->offset);
        }

        $this->sql = $sql;

        return $this;
    }

    /**
     * Builds an insert query.
     *
     * @param array $data Array of key and values to insert
     * @return string SQL statement
     */
    public function insert(array $data) {
        if (empty($this->table) || empty($data)) return;

        $sql = 'INSERT INTO '.
            $this->table;

        $sql .= ' ('.
            implode(',',
                array_keys($data)
            ).
        ') VALUES ('.
            implode(',',
                array_map(
                    array($this, 'quote'),
                    array_values($data)
                )
            ).
        ')';

        $this->sql = $sql;

        return $this;
    }

    /**
     * Builds an update query.
     *
     * @param array $data Array of key and values to insert
     * @return string SQL statement
     */
    public function update(array $data) {
        if (empty($this->table) || empty($data)) return;

        $sql = 'UPDATE '.
            $this->table;

        $values = array();
        foreach ($data as $key => $value) {
            $values[] = $key.'='.$this->quote($value);
        }
        $sql .= ' SET '.implode(',', $values);

        if (!empty($this->where)) {
            $sql .= ' WHERE '.implode('', $this->where);
        }

        $this->sql = $sql;

        return $this;
    }

    /**
     * Builds a delete query.
     *
     * @param array $data Array of key and values to insert
     * @return string SQL statement
     */
    public function delete($field = null, $value = null) {
        if (empty($this->table)) return;

        if ($field !== null) {
            $this->parseCondition($this->where, $field, $value);
        }

        $sql = 'DELETE FROM '.
            $this->table;

        if(!empty($this->where)) {
            $sql .= ' WHERE '.implode('', $this->where);
        }

        $this->sql = $sql;

        return $this;
    }

    /**
     * Gets the constructed SQL statement.
     *
     * @return string SQL statement
     */
    public function sql() {
        return $this->sql;
    }

    /**
     * Gets the min value for a specified field.
     *
     * @param string $field Field name
     */
    public function min() {
        return $this->value(
            'min_value',
            $this->select('MIN('.$field.') min_value')->sql()
        );
    }

    /**
     * Gets the max value for a specified field.
     *
     * @param string $field Field name
     */
    public function max() {
        return $this->value(
            'max_value',
            $this->select('MAX('.$field.') max_value')->sql()
        );
    }

    /**
     * Gets the sum value for a specified field.
     *
     * @param string $field Field name
     */
    public function sum() {
        return $this->value(
            'sum_value',
            $this->select('SUM('.$field.') sum_value')->sql()
        );
    }

    /**
     * Gets the average value for a specified field.
     *
     * @param string $field Field name
     */
    public function avg() {
        return $this->value(
            'avg_value',
            $this->select('AVG('.$field.') avg_value')->sql()
        ); 
    }

    /**
     * Gets a count of records for a table.
     *
     * @param string $field Field name
     */
    public function count($field = null) {
        return $this->value(
            'num_rows',
            $this->select('COUNT('.(empty($field) ? '*' : $field).') num_rows')->sql()
        );
    }

    /**
     * Parses a connection string into an object.
     *
     * @param string $connection Connection string
     * @return object Connection information
     */
    public function parseConnection($connection) {
        $url = @parse_url($connection);

       if (!isset($url['host'])) {
            throw new Exception('Database host must be specified in the connection string.');
        }

        $db = new stdClass;
        $db->type = $url['scheme'];
        $db->hostname = $url['host'];
        $db->database = isset($url['path']) ? substr($url['path'],1) : null;
        $db->username = isset($url['user']) ? $url['user'] : null;
        $db->password = isset($url['pass']) ? $url['pass'] : null;
        $db->port = isset($url['port']) ? $url['port'] : null;

        return $db;
    }

    /**
     * Parses a set of conditions.
     *
     * @param reference $array Array reference
     * @param string|array $field Database field
     * @param string $value Database value
     */
    protected function parseCondition(&$array, $field, $value = null, $escape = true) {
        if (is_string($field)) {
            list($field, $operator) = explode(' ', $field);
           
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
                    $condition = '=';
            }
 
            $join = (empty($array)) ? '' : (($field{0} == '|') ? ' OR ' : ' AND ');

            if (is_array($value)) {
                if (strpos($operator, '@') === false) $condition = ' IN ';
                $value = '('.implode(',', array_map(array($this, 'quote'), $value)).')';
            }
            else {
                $value = ($escape && !is_numeric($value)) ? $this->quote($value) : $value;
            }

            $array[] = $join.str_replace('|', '', $field).$condition.$value;
        }
        else if (is_array($field)) {
            foreach ($field as $key => $value) {
                $this->parseCondition($array, $key, $value, $escape);
            }
        }
    }

    /**
     * Gets the SQL text for field selection.
     *
     * @param string|array $fields Field name or array of field names
     * @return string Field names as a string
     */
    protected function parseFields($fields, $default = '') {
        if (is_string($fields)) {
            return $fields;
        }
        else if (is_array($fields)) {
            return implode(',', $fields); 
        }
        return $default;
    }

    /**
     * Wraps quotes around a string and escapes the content.
     *
     * @param string $value String value
     * @return string Quoted string
     */
    public function quote($value) {
        return '\''.$this->escape($value).'\'';
    }

    /**
     * Escapes special characters in a string.
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape($value) {
        if ($this->db !== null) {
            $db = $this->connect();

            switch ($this->db->type) {
                case 'mysqli':
                    return $db->real_escape_string($value);
                case 'mysql':
                    return mysql_real_escape_string($value, $db);
                case 'sqlite':
                    return sqlite_escape_string($value);
                case 'sqlite3':
                    return $db->escapeString($value); 
            }            
        }
        return str_replace(
            array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
            array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
            $value
        ); 
    }

    /**
     * Connects to the database.
     *
     * @return object Database instance
     */
    public function connect() {
        if ($this->db === null) {
            throw new Exception('Database is not been defined.');
        }

        static $db;

        if (!$db) {
            switch ($this->db->type) {
                case 'mysqli':
                    $db = new mysqli(
                        $this->db->hostname,
                        $this->db->username,
                        $this->db->password,
                        $this->db->database
                    );

                    if ($db->connect_error) {
                        throw new Exception('Connection error: '.$db->connect_error);
                    }

                    break;

                case 'mysql':
                    $db = mysql_connect(
                        $this->db->hostname,
                        $this->db->username,
                        $this->db->password
                    );

                    if (!$db) {
                        throw new Exception('Connection error: '.mysql_error());
                    }

                    mysql_select_db($this->db->database, $db);

                    break;

                case 'sqlite':
                    $db = sqlite_open($this->db->database, 0666, $error);

                    if (!$db) {
                        throw new Exception('Connection error: '.$error);
                    }

                    return $db;

                case 'sqlite3':
                    return new SQLite3($this->db->database);
            }
        }

        return $db;
    }

    /**
     * Executes a sql statement.
     *
     * @param string $sql SQL statement
     * @param object $db Database to run against
     * @return mixed SQL results
     */
    public function execute($sql = null) {
        if (!$sql) {
            $sql = $this->sql();
        }

        $result = null;

        $this->num_rows = 0;
        $this->affected_rows = 0;
        $this->insert_id = -1;
        $this->last_query = $sql;

        if ($this->stats_enabled) {
            $this->query_time = microtime(true);
        }

        $db = $this->connect();

        switch ($this->db->type) {
            case 'mysqli':
                $result = $db->query($sql);

                if (!$result) {
                    throw new Exception($db->error);
                }

                $this->num_rows = @$result->num_rows;
                $this->affected_rows = @$db->affected_rows;
                $this->insert_id = @$db->insert_id;

                break;

            case 'mysql':
                $result = mysql_query($sql, $db);

                if (!$result) {
                    throw new Exception(mysql_error());
                }

                $this->num_rows = mysql_num_rows($result);
                $this->affected_rows = mysql_affected_rows($db);
                $this->insert_id = mysql_insert_id($db);

            case 'sqlite':
                $result = sqlite_query($db, $sql);

                $this->num_rows = sqlite_num_rows($result);
                $this->affected_rows = sqlite_changes($db);
                $this->insert_id = sqlite_last_insert_rowid($db);

                return $result;

            case 'sqlite3':
                $result = $db->query($sql);

                if ($result === false) {
                    throw new Exception($db->lastErrorMsg());
                }

                $this->affected_rows = ($result) ? $db->changes() : 0;
                $this->insert_id = $db->lastInsertRowId();

                return $result;
        }

        if ($this->stats_enabled) {
            $time = microtime(true) - $this->query_time;
            $this->stats['query_time'] += $time;
            $this->stats['queries'][] = array(
                'query' => $sql,
                'time' => $time
            );
        }

        return $result;
    }

    /**
     * Fetch rows from a select query.
     *
     * @param string $sql SQL statement
     */
    public function fetch($sql = null) {
        if (!$sql) {
            if ($this->sql === null) $this->select();
            $sql = $this->sql();
        }

        $data = array();

        if (!empty($sql)) {
            $result = $this->execute($sql);

            switch ($this->db->type) {
                case 'mysqli':
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $result->close();
                    break;
           
                case 'mysql':
                    while ($row = mysql_fetch_assoc($result)) {
                        $data[] = $row;
                    }
                    mysql_free_result($result);
                    break;

                case 'sqlite':
                    $data = sqlite_fetch_all($result, SQLITE_ASSOC);
                    break;

                case 'sqlite3':
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $data[] = $row;
                        }
                        $this->num_rows = sizeof($data);
                    }
                    break;
            }
        }

        return $data;
    }

    /**
     * Fetch a single row from a select query.
     *
     * @param string $sql SQL statement
     */
    public function one($sql = null) {
        $data = $this->fetch($sql);

        return (!empty($data)) ? $data[0] : array();
    }

    /**
     * Fetch a value from a field.
     *
     * @param string $sql SQL statement
     */
    public function value($field, $sql = null) {
        $row = $this->one($sql);

        return (!empty($row)) ? $row[$field] : null;
    }

    /**
     * Gets the database instance.
     */
    public function getDB() {
        return $this->connect();
    }

    /**
     * Gets the query statistics.
     */
    public function getStats() {
        return $this->stats;
    }
}
?>
