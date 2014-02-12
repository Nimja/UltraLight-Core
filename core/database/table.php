<?php

namespace Core\Database;

/**
 * Basic table class for table creation and checking.
 */
class Table {
    const STRUCTURE_NOCHANGE = 'nochange';
    const STRUCTURE_CREATED = 'created';
    const STRUCTURE_UPDATED = 'updated';
    /**
     * The database we're connected to.
     * @var \Core\Database
     */
    private $_db;

    /**
     * Table name.
     * @var string
     */
    private $_table;

    /**
     * Escaped table name.
     * @var string
     */
    private $_tableEscaped;

    /**
     * Basic constructor.
     * @param \Core\Database $database
     * @param string $table
     */
    public function __construct($database, $table)
    {
        $this->_db = $database;
        $this->_table = $table;
        $this->_tableEscaped = $database->escape($table, true);
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return boolean table Exists or not.
     */
    public function exists()
    {
        $check = $this->_db->fetchFirstRow("SHOW TABLES LIKE {$this->_db->escape($this->_table)}");
        return !empty($check);
    }

    /**
     * Show columns for table.
     *
     * @param string $table
     * @return array of \Core\Database\Column
     */
    public function columns()
    {
        $columns = $this->_db->fetchRows("SHOW COLUMNS FROM {$this->_tableEscaped}");
        $result = array();
        foreach ($columns as $column) {
            $result[$column['Field']] = new Column($this->_db, $column, true);
        }
        return $result;
    }

    /**
     * Apply structure to the database, forcing recreation or not.
     * @param array $fields
     * @param string $force
     * @return boolean
     */
    public function applyStructure($fields, $force = false)
    {
        $result = self::STRUCTURE_NOCHANGE;
        if (!$this->exists() || $force) {
            $result = $this->_create($fields);
        } else {
            $result = $this->_update($fields);
        }
        return $result;
    }

    /**
     * Create table, dropping if exists.
     * @param array $fields
     * @return boolean
     */
    private function _create($fields)
    {
        $table = $this->_tableEscaped;
        if (\Core::$debug) {
            \Core::debug($table, 'Drop table if not exists');
        } else {
            $this->_db->query('DROP TABLE IF EXISTS ' . $table . ';');
        }
        //Basic create table functionality
        $default = new Column($this->_db);
        $sql = "CREATE TABLE {$table} (\n\t`id` {$default},\n";
        //Go over fields.
        foreach ($fields as $field => $type) {
            $eField = $this->_db->escape($field, true);
            $column = new Column($this->_db, $type);
            $sql .= "\t{$eField} {$column},\n";
        }
        $sql .= "\t PRIMARY KEY  (`id`) \n) ENGINE=innodb DEFAULT CHARSET=latin1;";
        if (\Core::$debug) {
            \Core::debug($sql, 'Creating table');
        } else {
            \Show::info($sql);
            $this->_db->query($sql);
        }
        return self::STRUCTURE_CREATED;
    }

    /**
     * Update the table.
     *
     * @param array $fields
     * @return boolean Success
     */
    private function _update($fields)
    {
        $table = $this->_tableEscaped;
        $db = $this->_db;
        // Get the current situation.
        $columns = $this->columns();
        // If the ID column does not exist, table was never properly created.
        $default = new Column($db);
        if (empty($columns['id'])) {
            throw new \Exception("{$this->_table} has no id field.");
        } else if (!$default->compare($columns['id'])) {
            throw new \Exception("{$this->_table} has wrong id field: {$columns['id']}");
        } else {
            unset($columns['id']);
        }
        // Get the desired situation.
        $desired = array();
        foreach ($fields as $field => $type) {
            $desired[$field] = new Column($db, $type);
        }
        $changes = $this->_getDifference($columns, $desired);
        // No changes.
        $result = !empty($changes);
        if ($result) {
            if (\Core::$debug) {
                \Core::debug($changes, "Changing table: $table");
            } else {
                $sql = "ALTER TABLE $table \n" . implode(",\n", $changes);
                $db->query($sql);
            }
        }
        return $result ? self::STRUCTURE_UPDATED : self::STRUCTURE_NOCHANGE;
    }

    /**
     * Get changes for desired from current..
     * @param array $columns
     * @param array $desired
     * @return array
     */
    private function _getDifference($columns, $desired)
    {
        // Do the compare.
        $changes = array();
        $prevField = $this->_db->escape('id', true);
        // Add/modify columns by comparison.
        foreach ($desired as $field => $column) {
            $efield = $this->_db->escape($field, true);
            if (empty($columns[$field])) {
                $changes[] = "ADD {$efield} {$column} AFTER {$prevField}";
            } else if (!$columns[$field]->compare($column)) {
                $cur = trim(implode(' ', $column));
                $compare = trim(implode(' ', $columns[$field]));
                if ($cur != $compare) {
                    $changes[] = "MODIFY {$efield} {$column} AFTER {$prevField}";
                }
            }
            unset($columns[$field]);
            $prevField = $efield;
        }
        // Drop columns that are superflous.
        foreach ($columns as $field => $column) {
            $efield = $this->_db->escape($field, true);
            $changes[] = "DROP {$efield}";
        }
        return $changes;
    }

}
