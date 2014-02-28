<?php
namespace Core;
/**
 * Nice MySQL database interface class.
 *
 * Uses mysqli objects for connections.
 */
class Database
{
    const SEARCH_PREG = '/(.+)(\|[\:\=\<\>\!])(.+)/';
    const SEARCH_VALUE = 'value';
    const SEARCH_OPERATION = 'operation';
    /**
     * Array of connections
     *
     * @var array
     */
    private static $_instances = array();
    /**
     * The mysqli connection object.
     * @var \mysqli
     */
    private $_mysqli;
    /**
     * Last executed query.
     * @var \mysqli_result
     */
    private $_result;

    /**
     * Connect to a database, using settings.
     * @param array $settings With server, username, password and database.
     */
    private function __construct($settings)
    {
        $mysqli = new \mysqli($settings['server'], $settings['username'], $settings['password'], $settings['database']);
        if ($mysqli->connect_errno) {
            throw new \Exception("Unable to connect with credentials: {$mysqli->connect_error}");
        }
        $this->_mysqli = $mysqli;
    }

    /**
     * Run Query, returning the query resource.
     *
     * @param string $sql The MySQL query.
     * @param boolean buffered
     * @return boolean|mysqli_result
     */
    public function query($sql, $buffered = true)
    {
        \Core::debug($sql, 'Executing SQL');
        if ($buffered) {
            $this->_result = $this->_mysqli->query($sql);
        } else {
            $this->_result = $this->_mysqli->query($sql, MYSQLI_USE_RESULT);
        }
        if ($this->_result === false) {
            throw new \Exception($sql . "\n" . $this->_mysqli->error, $this->_mysqli->errno);
        }
        return $this->_result;
    }

    /**
     * Get last resource.
     * @return \mysqli_result
     */
    public function getRes() {
        return $this->_result;
    }

    /**
     * Return number of affected or returned rows of the last query.
     *
     * @return int The number of results.
     */
    public function count()
    {
        $result = 0;
        if (empty($this->_result)) {
            throw new \Exception("No valid last query.");
        } else if ($this->_result instanceof \mysqli_result) {
            $result = $this->_result->num_rows;
        } else {
            $result = $this->_mysqli->affected_rows;
        }
        return $result;
    }

    /**
     * Get an array with all the results.
     *
     * @param string|null Query or use previous result.
     * @return array Associative Array for this result.
     */
    public function fetchRows($sql = null)
    {
        $res = $sql ? $this->query($sql): $this->_result;
        $result = array();
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Get an array with all the results.
     *
     * @param \mysqli_result $res A MySQLi result resource.
     * @return array Associative Array for this result.
     */
    public function fetchValues($field, $res = null)
    {
        if (empty($res)) {
            $res = $this->_result;
        }
        return $this->fetchList($field, $field, $res);
    }

    /**
     * Fetch results as a list, using keyField and valueField for example for drowpdowns.
     *
     * @param \mysqli_result $res A MySQLi result resource.
     * @return array
     */
    public function fetchList($keyField, $valueField, $res = null)
    {
        if (empty($res)) {
            $res = $this->_result;
        }
        $result = array();
        while ($row = $res->fetch_assoc()) {
            $result[$row[$keyField]] = $row[$valueField];
        }
        return $result;
    }

    /**
     * Run a query and get the first result, if any. Good for checks or single objects.
     *
     * @param string|null Query or use previous result.
     * @return array|null Associative Array for this result.
     */
    public function fetchFirstRow($sql = null)
    {
        $res = $sql ? $this->query($sql): $this->_result;
        $result = null;
        if ($res && $res->num_rows > 0) {
            $result = $res->fetch_assoc();
            $res->free();
        }
        \Core::debug($result, 'Result:');
        return $result;
    }

    /**
     * Fetch the first value of a query of the first row.
     *
     * If none are found, null is returned.
     *
     * @param string|null Query or use previous result.
     * @return type
     */
    public function fetchFirstValue($sql = null)
    {
        $result = $this->fetchFirstRow($sql);
        return !empty($result) ? array_shift($result) : null;
    }

    /**
     * Fetch a flat array of the first column of hte result.
     *
     * @param string|null Query or use previous result.
     * @return array
     */
    public function fetchColumn($sql = null)
    {
        $res = $sql ? $this->query($sql): $this->_result;
        $result = array();
        if ($res !== true) {
            while ($row = $res->fetch_assoc()) {
                $result[] = array_shift($row);
            }
        }
        $res->free();
        return $result;
    }

    /**
     * Escape value neatly for database.
     *
     * Empty strings will be translated to "NULL".
     *
     * @param mixed $value
     * @param boolean $backticks If we want to escape DB/column names.
     * @param boolean $forceQuotes If we want to enforce quotes (for numeric values)
     * @return mixed A neatly escaped value (or array with values)
     */
    public function escape($value, $backticks = false, $forceQuotes = false)
    {

        if (!is_array($value)) { //Any other value.
            $quote = ($backticks) ? '`' : "'";
            if ($backticks && strpos($value, '.') !== false) {
                $parts = explode('.', $value);
                $result = implode('.', $this->escape($parts, true, $forceQuotes));
            } else if (blank($value)) {
                $result = 'NULL';
            } else {
                $result = $this->_mysqli->real_escape_string(trim($value));
                if (!is_numeric($result) || $forceQuotes) {
                    $result = $quote . $result . $quote;
                }
            }
        } else { //If it's an array.
            $result = array();
            foreach ($value as $key => $val) {
                $result[$key] = $this->escape($val, $backticks, $forceQuotes);
            }
        }
        return $result;
    }

    /**
     * Insert into table with associative array
     *
     * @param string $table
     * @param array $values
     * @return int The newly inserted ID.
     * @throws \Exception
     */
    public function insert($table, $values)
    {
        if (empty($table) || empty($values)) {
            throw new \Exception("Attempting to insert with empty data.");
        }
        $sql = "INSERT INTO {$this->escape($table, true)}
            SET {$this->_arrayToSql($values)}";
        $this->query($sql);
        $result = $this->_mysqli->insert_id;
        return $result;
    }

    /**
     * Update a table with $values and $where
     *
     * @param string $table
     * @param array @values
     * @param string|array $where
     * @return int $id with which we updated.
     * @throws \Exception
     */
    public function update($table, $values, $search)
    {
        if (empty($table) || empty($values)) {
            throw new \Exception("Attempting to update with empty data.");
        }
        if (empty($search)) {
            throw new \Exception("Attempting to update without find.");
        }
        $result = false;
        if (!empty($table) && !empty($values)) {
            $sql = "UPDATE {$this->escape($table, true)}
                SET {$this->_arrayToSql($values)}
                WHERE {$this->searchToSql($search)}";
            $this->query($sql);
            $result = $this->count();
        }
        return $result;
    }

    /**
     * Translate array to MySQL update/insert format.
     *
     * @param array $data
     * @return string Values of the array in MySQL friendly update/insert format.
     */
    protected function _arrayToSql($values)
    {
        #Escape the values.
        $values = $this->escape($values);

        $parts = array();
        foreach ($values as $column => $value) {
            $parts[] = $this->escape($column, true) . '=' . $value . '';
        }
        return implode(', ', $parts);
    }

    /**
     * Get a table object, to use for various functions.
     * @param string $table
     * @return Database\Table
     */
    public function table($table)
    {
        return new Database\Table($this, $table);
    }

    /**
     * Create backup SQL for tables.
     *
     * @param string $tables Which table(s) to backup.
     * @return string MySQL 'queries' with backup data.
     */
    public function backup($tables = '*')
    {
        #Get the table list.
        if ($tables == '*') {
            $tables = $this->fetchColumn('SHOW TABLES');
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }
        //Go over each table for backukp.
        $result = array();
        foreach ($tables as $table) {
            $table = $this->escape($table, true);
            //Add the 'drop if exists'
            $result [] = 'DROP TABLE IF EXISTS ' . $table . ';';

            #Add the table creation string (thank god MySQL has this)
            $row = $this->fetchFirstRow('SHOW CREATE TABLE ' . $table);
            array_shift($row);
            $result [] = array_shift($row);

            $res = $this->query("SELECT * FROM $table ORDER BY id ASC");
            if ($res->num_rows > 0) {
                $result[] = Database\Export::sql($this, $table, $res);
            }
            #Empty space between each table.
            $result[] = "\n";
        }
        return implode("\n\n", $result);
    }

    /**
     * Search a table with simple searching mechanism.
     *
     * Can then call fetchX afterwards.
     *
     * @param string $table
     * @param string|array $search
     * @param array $settings
     * @return \self
     */
    public function search($table, $search = null, $settings = null)
    {
        $table = $this->escape($table, true);
        $where = empty($search) ? '1' : $this->searchToSql($search);
        $settings = is_array($settings) ? $settings : array();
        $order = getKey($settings, 'order', 'id ASC');
        $limit = getKey($settings, 'limit');
        $limitString = $limit ?  "LIMIT {$limit}" : '';
        $fields = $this->_getFields(getKey($settings, 'fields'));
        $sql = "SELECT {$fields} FROM {$table} WHERE {$where} ORDER BY {$order} {$limitString}";
        $this->query($sql);
        return $this;
    }
    /**
     * Get fields formatted for query..
     * @param type $settings
     * @return type
     */
    private function _getFields($fields)
    {
        $result = '*';
        if (!empty($fields)) {
            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }
            $result = implode(', ', $fields);
        }
        return $result;
    }

    /**
     * Do a search that can be used through JS, using operators and values.
     *
     * |= -> =<br />
     * |: -> LIKE<br />
     * |> -> > <br />
     * |< -> < <br />
     * |! -> <> <br />
     *
     * Combine searches with ;
     *
     * @param string $search
     * @return string
     */
    public function searchToSql($search)
    {
        $where = array();
        if (empty($search)) {
            $where = null;
        } else if (is_int($search)) {
            $where[] = "id = $search";
        } else {
            $searches = is_array($search) ? $search : $this->_parseSearch($search);
            $where = $this->_parseSearches($searches);
        }
        return empty($where) ? '1' : implode(' AND ', $where);
    }

    /**
     * Parse search string into nice array.
     *
     * @param type $search
     * @return array
     */
    protected function _parseSearch($search)
    {
        $result = array();
        $search = html_entity_decode($search);
        $searches = explode(';', $search);
        foreach ($searches as $search) {
            $matches = null;
            $found = preg_match(self::SEARCH_PREG, $search, $matches);
            if (!$found || count($matches) != 4) {
                continue;
            }
            $result[trim($matches[1])] = array(
                self::SEARCH_VALUE => trim($matches[3]),
                self::SEARCH_OPERATION => $matches[2],
            );
        }
        return $result;
    }

    /**
     * Parse search array into where array.
     * @param array $searches
     * @return array
     */
    private function _parseSearches($searches)
    {
        $where = array();
        foreach ($searches as $field => $details) {
            if (!is_array($details)) {
                $details = array('value' => $details);
            }
            $value = getKey($details, self::SEARCH_VALUE);
            $operation = getKey($details, self::SEARCH_OPERATION, '|=');
            $condition = $this->escape($field, true);
            if ($value == 'null') {
                $condition .= ($operation != '|!') ? ' IS NULL' : ' IS NOT NULL';
            } else {
                $operand = '=';
                switch ($operation) {
                    case '|!': $operand = '!=';
                        break;
                    case '|<': $operand = '<';
                        break;
                    case '|>': $operand = '>';
                        break;
                    case '|:':
                        $operand = 'LIKE';
                        $value = "%$value%";
                        break;
                }
                $condition .= " $operand {$this->escape($value)}";
            }
            $where[] = $condition;
        }
        return $where;
    }

    /**
     * Get an instanced Database object.
     * @param string $database
     * @return Library_Database
     */
    public static function instance($database = null)
    {
        $database = $database ? : \Config::system()->get('database', 'default');
        if (empty(self::$_instances[$database])) {
            self::$_instances[$database] = new self(self::_getSettingsForDatabase($database));
        }
        return self::$_instances[$database];
    }

    /**
     * Get settings for this database.
     * @param string $database
     * @return array
     * @throws \Exception
     */
    private static function _getSettingsForDatabase($database)
    {
        $connections = \Config::system()->get('database', 'connection');
        if (empty($connections)) {
            throw new \Exception('No connections configured.');
        }
        if (empty($connections[$database])) {
            throw new \Exception("Connection for $database not configurd.");
        }
        $settings = $connections[$database];
        if (!is_array($settings)) {
            throw new \Exception("Connection for $database not an array.");
        }
        $requiredKeys = array('server' => true, 'username' => true, 'password' => true, 'database' => true);
        $diff = array_diff_key($requiredKeys, $settings);
        if (!empty($diff)) {
            throw new \Exception("Connection for $database missing fields: " . implode(', ', array_keys($diff)));
        }
        return $settings;
    }
}