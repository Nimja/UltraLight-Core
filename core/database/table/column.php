<?php
namespace Core\Database\Table;
class Column
{
    /**
     * The name of this field.
     * @var type
     */
    public $field;
    /**
     * The type (+length) of this column.
     * @var string
     */
    public $type;
    /**
     * Allowed null for this column.
     * @var string
     */
    public $null;
    /**
     * Default value of this column.
     * @var string
     */
    public $default;
    /**
     * Additional variable of this column.
     * @var string
     */
    public $extra;
    /**
     * The database, for escaping.
     * @var \Core\Database
     */
    private $_db;

    /**
     * Make column info for data.
     *
     * @param \Core\Database $db
     * @param string|array $field A string consisting of 1 to 5 parts, divided by |
     *
     * name (int, text, bool, varchar, etc.) <br />
     * length (0 = no length given) <br />
     * default value<br />
     * unsigned (boolean)<br />
     * extra (like auto-increment or curdate).
     *
     * @return string|array The colum, as formatted by Type.
     */
    public function __construct($db, $field = null, $fromDb = false)
    {
        $this->_db = $db;
        if (empty($field)) {
            $this->type = 'int(11) unsigned';
            $this->null = 'NOT null';
            $this->default = null;
            $this->extra = 'auto_increment';
        } else if (!$fromDb) {
            $this->_setDetails($field);
        } else {
            $this->_setFromDb($field);
        }
    }

    /**
     * Set details from field.
     * @param type $field
     */
    private function _setDetails($field)
    {
        if (!is_array($field)) {
            throw new \Exception('Cannot translate column.');
        }
        $type =  getKey($field, 'type', 'int');
        $length = getKey($field, 'length', 0);
        $default = getKey($field, 'default', '');
        $unsigned = !empty($field['unsigned']);
        $null = !empty($field['null']);
        $extra = getKey($field, 'extra', '');
        //If length has not been defined.
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
            if ($null && empty($default)) {
                $default = null;
            }
        } else if (substr($type, -4) == 'text') {
            $length = 0;
            $null = true;
            $default = '';
        } else if ($type == 'bool') {
            $length = 1;
            $type = 'tinyint';
            $default = intval($default);
        } else if ($type == 'date') {
            $length = null;
        } else if ($type == 'timestamp') {
            $default = ' CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP';
        } else if ($type == 'varchar' || $type == 'char') {
            $null = true;
            if (empty($length)) {
                $length = 127;
            }
        }
        $length = !empty($length) ? '(' . $length . ')' : '';
        $this->type = $type . $length . $typeExtra;
        $this->null = ($null) ? 'null' : 'NOT null';
        $this->default = $default;
        $this->extra = $extra;
    }

    /**
     * Set details from SHOW COLUMNS result.
     * @param type $field
     */
    private function _setFromDb($column)
    {
        $this->type = $column['Type'];
        $this->null = ($column['Null'] == 'YES') ? 'null' : 'NOT null';
        $this->default = $column['Default'];
        $this->extra = $column['Extra'];
    }

    /**
     * Get column as SQL.
     * @return type
     */
    public function __toString()
    {
        $result = "{$this->type} {$this->null}";
        if (!blank($this->default)) {
            $result .= " default {$this->_db->escape($this->default)}";
        }
        if (!empty($this->extra)) {
            $result .= " {$this->extra}";
        }
        return $result;
    }

    /**
     * Compare column with this one.
     * @param \Core\Database\Column $column
     * @return boolean True if columns are identical.
     */
    public function compare($column)
    {
        return "$this" == "$column";
    }


}
