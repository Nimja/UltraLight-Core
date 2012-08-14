<?php

#Include this with:
#	Load::library('database');
#Database class to connect properly.

class Database
{

    /**
     * Last mysql query resource
     * 
     * @var resource
     */
    public $last = '';

    /**
     * Last mysql query
     * 
     * @var string
     */
    public $lastQuery = '';

    /**
     * Last MySQL query.
     * 
     * @var string
     */
    protected $type = array();

    /**
     * Database Resource
     * 
     * @var resource
     */
    protected $db;

    /**
     * Array of connections
     * 
     * @var array
     */
    private static $connections = array();

    public function __construct($database = null)
    {
        if (empty($database))
            $database = 0;
        $connections = &self::$connections;
        #Prevent multiple connections to the same database within the same request, just in case.
        #this allows for multiple instances without multiple connections
        if (!empty($connections[$database])) {
            $this->db = & $connections[$database];
            return;
        }

        $initial = $database;

        #Make sure these are defined.
        $config = &$GLOBALS['config'];
        $default = !empty($config['database']) ? $config['database'] : null;
        $databases = !empty($config['databases']) ? $config['databases'] : null;
        if (empty($databases))
            show_exit('No databases configured!');

        if (empty($database))
            $database = $default;

        #Check if the database exists
        if (
                empty($database)
                || empty($databases)
                || empty($databases[$database])
        ) {
            show_exit($database, 'Wrong database requested');
        }


        #Check if the database is (fairly) proper.
        $settings = $databases[$database];
        if (
                empty($settings['server'])
                || empty($settings['database'])
                || empty($settings['username'])
                || empty($settings['password'])
        ) {
            show_exit($database, 'Database not configured properly');
        }
        #Connect to database.
        $this->db = mysql_connect($settings['server'], $settings['username'], $settings['password']) or show_exit($settings['server'], 'Unable to connect to Database');
        #Select database
        mysql_select_db($settings['database'], $this->db) or show_exit($settings['database'], 'Unable to Select Database');

        $connections[$database] = & $this->db;
        if ($initial != $database)
            $connections[$initial] = & $this->db;
    }

    /**
     * Disconnect the database connection (hardly used..)
     * 
     */
    public function disconnect()
    {
        mysql_close($this->db);
    }

    /**
     * Run Query, returning the query resource.
     * 
     * @param string $sql The MySQL query.
     * @return resource The MySQL result resource.
     */
    public function query($sql)
    {
        $sql = trim($sql); //Be sure to remove white-spaces.
        $this->numrows = null;
        if (DEBUG)
            show($sql, 'Query:');

        $this->lastQuery = $sql;
        $this->last = mysql_query($sql, $this->db) or show_error(mysql_error($this->db), '<b>Query failed: </b>' . $sql);

        $this->type[$this->last] = strtolower(substr($sql, 0, 1)); //Check the first letter.
        return $this->last;
    }

    /**
     * Return count of the affected rows (or of select)
     * 
     * @param resource $res A MySQL result resource.
     * @return int The number of results.
     */
    public function count($res = null)
    {
        $type = '';
        if (empty($res) || $res == $this->last) {
            $res = $this->last;
        }
        $type = $this->type[$res];
        $result = 0;
        switch ($type) {
            case 'u':
            case 'r':
            case 'i':
            case 'd': $result = mysql_affected_rows($this->db);
                break;
            default: $result = mysql_num_rows($res);
                break;
        }
        return $result;
    }

    /**
     * Get the next row of this result.
     * 
     * @param resource $res A MySQL result resource.
     * @return array Associative Array for this result.
     */
    public function getRow($res = null)
    {
        if (empty($res))
            $res = $this->last;
        return mysql_fetch_assoc($res);
    }

    /**
     * Return count of the affected rows (or of select)
     * 
     * @param resource $res A MySQL result resource.
     * @param boolean $csv Export to CSV instead of HTML table.
     * @return int The number of results.
     */
    public function export($res = null, $csv = false)
    {
        if (empty($res))
            $res = $this->last;
        $ln = "\n";

        $result = '';
        if ($this->count($res) > 0) {
            #Begin output.
            $result = ($csv) ? '' : '<table>' . $ln;

            #Get first row.
            $row = $this->getRow($res);
            if ($row) {
                $keys = array_keys($row);
                #Header
                $result .= ( $csv) ? implode(';', $keys) . $ln : '<tr><th>' . implode('</th><th>', $keys) . '</th></tr>' . $ln;
            }
            #Rows
            while ($row !== false) {
                $result .= ( $csv) ? implode(';', $row) . $ln : '<tr><td>' . implode('</td><td>', $row) . '</td></tr>' . $ln;
                $row = $this->getRow($res);
            }
            $result .= ( $csv) ? '' : '</table>' . $ln;
        }
        return $result;
    }

    /**
     * Get an array with all the results.
     * 
     * @param resource $res A MySQL result resource.
     * @param string $field Only get results for this field.
     * @return array Associative Array for this result.
     */
    public function getResults($res = null, $field = null)
    {
        if (empty($res))
            $res = $this->last;
        $result = array();
        $do = !empty($field);
        while ($row = $this->getRow($res)) {
            if ($do)
                $result[$row[$field]] = $row[$field];
            else
                $result[] = $row;
        }
        return $result;
    }

    /**
     * Get the results in list format, $id => $fieldValue
     * 
     * @param string $id The ID field
     * @param string $field The Field used for display.
     * @param string $query The MySQL query to get this list.
     * @return array Associative Array for this result.
     */
    public function getList($id, $field, $query)
    {
        $res = $this->query($query);
        $result = array();
        while ($row = $this->getRow($res)) {
            $result[$row[$id]] = $row[$field];
        }
        $this->free($res);
        return $result;
    }

    /**
     * Free this resultset.
     * 
     * @param resource $res A MySQL result resource.
     * @return boolean Success
     */
    public function free($res = null)
    {
        if (empty($res))
            $res = $this->last;
        #Clear the result type.
        unset($this->type[$res]);
        return mysql_free_result($res);
    }

    /**
     * Run a query and get the first result, if any. Good for checks or single objects.
     * 
     * @param string $sql The MySQL query.
     * @return array|null Associative Array for this result.
     */
    public function fetchRow($sql)
    {
        $result = $this->query($sql);

        if ($this->count($result) > 0) {
            $value = $this->getRow($result);
        } else {
            $value = null;
        }
        $this->free();
        if (DEBUG)
            show($value, 'Result:');
        return $value;
    }

    public function run($sql)
    {
        return $this->fetchRow($sql);
    }

    /**
     * Fetch the first value of a query of the first row.
     * 
     * If none are found, null is returned.
     * 
     * @param string $sql
     * @return type
     */
    public function fetchValue($sql)
    {
        $result = $this->fetchRow($sql);
        return !empty($result) ? array_shift($result) : null;
    }

    /**
     * Escape value neatly for database.
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
            $result = mysql_real_escape_string(trim($value), $this->db);
            if (!is_numeric($result) || $forceQuotes) {
                $result = $quote . $result . $quote;
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
     */
    public function insert($table, $values)
    {
        $result = false;
        if (!empty($table) && !empty($values)) {
            $sql = 'INSERT INTO ' . $this->escape($table, true) . ' SET ' . $this->_arrayToSql($values);

            $this->query($sql);
            $result = mysql_insert_id($this->db);
        }
        return $result;
    }

    /**
     * Update a table with $values and $where
     * 
     * @param string $table
     * @param array @values
     * @param string $where
     * @return int $id with which we updated.
     */
    public function update($table, $values, $where = '')
    {
        $result = false;
        if (!empty($table) && !empty($values)) {
            $sql = 'UPDATE ' . $this->escape($table, true) . ' SET ' . $this->_arrayToSql($values);

            if (!empty($where))
                $sql .= ' WHERE ' . $where;
            $this->query($sql);
            $result = mysql_insert_id($this->db);
        }
        return $result;
    }

    /**
     * Delete from table, where...
     * 
     * @param string $table
     * @param string $where
     * @return int Number of deleted rows.
     */
    public function delete($table, $where = '')
    {
        $result = false;
        if (!empty($table)) {
            $sql = 'DELETE FROM ' . $this->escape($table, true) . '';

            if (!empty($where))
                $sql .= ' WHERE ' . $where;
            $res = $this->query($sql);

            $result = $this->count();
        }
        return $result;
    }

    /**
     * Insert or Update, based on key
     * 
     * @param string $table
     * @param array $values
     * @param array $update Optional alternate values for update.
     * @return int $id with which we updated.
     */
    public function insertOrUpdate($table, $values, $update = array())
    {
        $result = false;
        if (!empty($table) && !empty($values)) {
            $sql = 'INSERT INTO ' . $this->escape($table, true) . ' SET ';
            #Escape the values.
            $values = $this->_arrayToSql($values);

            $update = empty($update) ? $values : $this->_arrayToSql($update);

            $sql .= $values . ' ON DUPLICATE KEY UPDATE ' . $update;

            $this->query($sql);
            $result = mysql_insert_id($this->db);
        }
        return $result;
    }

    /**
     * Update a table with 'sort' values, using an array of ID's
     *
     * @param string $table
     * @param string $sortField
     * @param array $ids
     * @param string $idField
     * @param int $startVal
     * @return string Values of the array in MySQL friendly update/insert format.
     */
    public function updateSort($table, $ids, $sortField = 'sort', $idField = 'id', $startVal = 0)
    {
        $sortField = $this->escape($sortField, true);
        $idField = $this->escape($idField, true);

        $sql = 'UPDATE ' . $this->escape($table, true) . ' SET ' . $sortField . ' = CASE ' . $idField;

        $cur = intval($startVal);
        $ids = $this->escape($ids);
        #
        foreach ($ids as $id) {
            $sql .= "\nWHEN $id THEN $cur";
            $cur++;
        }
        $sql .= "\nELSE $sortField \nEND";

        $this->query($sql);
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
     * Add more to the where clause.
     * 
     * @param array $where
     * @param string $field
     * @param string|array $value
     * @param string $method
     */
    function whereOr(&$where, $field, $value, $method = '=')
    {
        if (!empty($value)) {
            #Put the value in an array, even if it's singular.
            if (!is_array($value))
                $values = array($value);
            else
                $values = $value;

            $values = $this->escape($values);
            $field = $this->escape($field, true);
            $parts = array();
            foreach ($values as $value) {
                $parts[] = $field . ' ' . $method . ' ' . $value;
            }
            #Add brackets if it's multiple options.
            $where[] = (count($parts) == 1) ? $parts[0] : '(' . implode(' OR ', $parts) . ')';
        }
    }

    #Help with where clauses.

    function whereLike(&$where, $field, $value)
    {
        $this->whereOr($where, $field, $value, 'LIKE');
    }

    function whereBetween(&$where, $field, $from, $to)
    {
        $fe = !empty($from);
        $te = !empty($to);

        $field = $this->escape($field, true);
        $from = $this->escape($from);
        $to = $this->escape($to);

        if ($fe && $te) {
            $where[] = '(' . $field . ' BETWEEN ' . $from . ' AND ' . $to . ' )';
        } elseif ($fe) {
            $where[] = $field . ' >= ' . $from;
        } elseif ($te) {
            $where[] = $field . ' <= ' . $to;
        }
    }

    function whereMake(&$where)
    {
        return (count($where) > 0) ? ' WHERE ' . implode(' AND ', $where) . ' ' : '';
    }

    /**
     * how what we are connected to with the __toString
     * 
     * @return string Information about this connection.
     */
    public function __toString()
    {
        return 'Connected to: ' . $this->db;
    }

    /**
     * Check if a table exists.
     * 
     * @param string $table
     * @return boolean table Exists or not.
     */
    public function table_exists($table)
    {
        $check = $this->run('SHOW TABLES LIKE ' . $this->escape($table));
        return !empty($check);
    }

    /**
     * Show columns for table.
     * 
     * @param string $table
     * @return array With column info.
     */
    public function show_columns($table)
    {
        $res = $this->query('SHOW COLUMNS FROM ' . $this->escape($table, true));
        $result = array();
        while ($row = $this->getRow($res)) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Check if a table exists.
     * 
     * This is a very simple method for most table types.
     * 
     * Standard 'id' autoincrement field always present as first field.
     * 
     * @param string $table
     * @param array $fields An array with type (int/bool/varchar, etc.) | length | default 
     */
    public function create_table($table, $fields, $force = false)
    {

        if (!$force && $this->table_exists($table))
            return false;

        $table = $this->escape($table, true);

        #Drop table of the same name, else we cannot create it.
        $this->query('DROP TABLE IF EXISTS ' . $table . ';');

        #Basic create table functionality
        $sql = 'CREATE TABLE ' . $table . ' (' . "\n\t" . '`id` int(11) UNSIGNED NOT null auto_increment,' . "\n";

        #Go over fields.
        foreach ($fields as $field => $type) {
            $column = $this->makeColumn($type);

            $sql .= "\t" . $this->escape($field, true) . ' ' . $column . ",\n";
        }
        $sql .= "\t" . 'PRIMARY KEY  (`id`)' . "\n" . ') ENGINE=innodb DEFAULT CHARSET=latin1;';

        $this->query($sql);
        return true;
    }

    /**
     * Update (or install) the table.
     * 
     * @param string $table
     * @param array $fields
     * @return boolean Success 
     */
    public function update_table($table, $fields)
    {
        if (!$this->table_exists($table)) {
            return $this->create_table($table, $fields, true);
        }
        $table = $this->escape($table, true);


        #Get the current situation.
        $res = $this->query('SHOW COLUMNS FROM ' . $table);
        $indb = array();
        while ($row = $this->getRow($res)) {
            $field = $row['Field'];
            unset($row['Field']);
            unset($row['Key']);
            $row['Null'] = ($row['Null'] == 'YES') ? 'null' : 'NOT null';
            $dbcolumns[$field] = $row;
        }
        #If the ID column does not exist, table was never properly created.
        if (empty($dbcolumns['id']) || $dbcolumns['id']['Type'] != 'int(11) unsigned') {
            show_exit($table, 'Database was not properly installed, force install will remove all contents...');
        } else {
            unset($dbcolumns['id']);
        }

        $this->free($res);

        #Get the desired situation.
        $desired = array();
        foreach ($fields as $field => $type) {
            $desired[$field] = $this->makeColumn($type, false);
        }

        #DO the compare.
        $changes = array();
        $prevField = '`id`';
        #Add/modify columns by comparison.
        foreach ($desired as $field => $column) {
            $efield = $this->escape($field, true);
            if (empty($dbcolumns[$field])) {

                $changes[] = 'ADD ' . $efield . ' ' . $this->makeColumn($fields[$field]) . ' AFTER ' . $prevField;
            } else {
                $dbcur = $dbcolumns[$field];

                $cur = trim(implode(' ', $column));
                $compare = trim(implode(' ', $dbcur));
                if ($cur != $compare) {
                    $changes[] = 'MODIFY ' . $efield . ' ' . $this->makeColumn($fields[$field]) . ' AFTER ' . $prevField;
                }
            }
            unset($dbcolumns[$field]);
            $prevField = $efield;
        }
        #Drop columns that are superflous.
        foreach ($dbcolumns as $field => $column) {
            $efield = $this->escape($field, true);
            $changes[] = 'DROP ' . $efield;
        }

        #No changes.
        if (empty($changes))
            return false;

        $sql = 'ALTER TABLE ' . $table . "\n";
        $sql .= implode(",\n", $changes);

        #Execute the alter table query.
        $this->query($sql);

        return true;
    }

    /**
     * 
     * @param string $type A string consisting of 1 to 3 parts, divided by |
     * 
     * name (int, text, bool, varchar, etc.) <br />
     * length (0 = no length given) <br />
     * default value.
     * 
     * @return string The colum, as formatted by Type. 
     */
    protected function makeColumn($field, $toString = true)
    {
        if (is_array($field)) {
            $type = isset($field['type']) ? $field['type'] : 'int';
            $length = isset($field['length']) ? intval($field['length']) : 0;
            $default = isset($field['default']) ? intval($field['default']) : '';
            $unsigned = !empty($field['unsigned']);
            $null = !empty($field['null']);
        } else {
            $parts = explode('|', $field);
            $type = array_shift($parts);
            $length = !empty($parts) ? intval(array_shift($parts)) : 0;
            $default = !empty($parts) ? array_shift($parts) : '';
            $unsigned = false;
            $null = false;
        }

        #If length has not been defined.
        $typeExtra = '';
        if (substr($type, -3) == 'int') {
            $default = intval($default);
            if (empty($length)) {

                switch ($type) {
                    case 'tinyint': $length = 4;
                        break;
                    case 'smallint': $length = 6;
                        break;
                    case 'mediumint': $length = 9;
                        break;
                    case 'bigint': $length = 20;
                        break;
                    default: $length = 11;
                        break;
                }
            }
            if ($unsigned) {
                $typeExtra = ' unsigned';
            }
        } else if (substr($type, -4) == 'text') {
            $length = 0;
            $null = true;
            $default = '';
        } else if ($type == 'bool') {
            #Booleans are tinyints with length 1.
            $length = 1;
            $type = 'tinyint';
            $default = intval($default);
        } else if ($type == 'timestamp') {
            #Timestamps are filled automatically
            $default = ' default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP';
        } else if ($type == 'varchar') {
            $null = true;
            #Default length for VarChar.
            if (empty($length))
                $length = 127;
        }
        $length = !empty($length) ? '(' . $length . ')' : '';

        $column = array(
            'type' => $type . $length . $typeExtra,
            'null' => ($null) ? 'null' : 'NOT null',
            'default' => $default,
            'extra' => $extra,
        );
        if (!$toString) {
            return $column;
        } else {
            $colsql = $column['type'] . ' ' . $column['null'];
            if (!blank($column['default'])) {
                $colsql .= ' default ' . $this->escape($column['default']);
            }
            return $colsql;
        }
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
            $tables = array();
            $res = $this->query('SHOW TABLES');
            while ($row = $this->getRow($res)) {
                $tables[] = array_shift($row);
            }
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        #Backup each table.
        $result = '';
        foreach ($tables as $table) {
            $table = $this->escape($table, true);

            #Add the 'drop if exists'
            $result.= 'DROP TABLE IF EXISTS ' . $table . ';';

            #Add the table creation string (thank god MySQL has this)
            $row = $this->run('SHOW CREATE TABLE ' . $table);
            array_shift($row);
            $result.= "\n\n" . array_shift($row) . ";\n\n";

            $res = $this->query('SELECT * FROM ' . $table);

            #Every 
            $count = 0;
            if ($this->count($res) > 0) {
                while ($row = mysql_fetch_assoc($res)) {
                    $values = array_map('mysql_real_escape_string', array_values($row));

                    if ($count == 0) {
                        $keys = array_keys($row);

                        $result .= 'INSERT INTO ' . $table . ' (`' . implode('`,`', $keys) . '`) VALUES ' . "\n\t";
                    } else {
                        $result .= "\n\t,";
                    }
                    $result .= '("' . implode('", "', $values) . '")';
                    $count++;
                    if ($count > 100) {
                        $result .= ";\n";
                        $count = 0;
                    }
                }
                $result .= ';';
            }
            #Empty space between each table.
            $result.="\n\n\n";
        }
        return $result;
    }

    /**
     * Execute multiple queries, with comments, etc.
     * 
     * Max 1 query per line, but queries can span multiple lines. 
     * End queries with ;
     * 
     * @param string $table
     * @return array With column info.
     */
    function execute($sql)
    {
        #Split SQL according to ;'s outside of strings.
        $lines = explode("\n", $sql);
        $query = '';
        foreach ($lines as $line) {
            $query .= "\n" . $line;
            $query = trim($query);
            if (substr($query, -1) == ';') {
                $this->query($query);
                $query = '';
            }
        }
        #Final query, because someone didn't end it with a ;
        if (!empty($query)) {
            $this->query($query);
        }
    }

}