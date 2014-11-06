<?php

namespace Core\Model;

/**
 * Class reflection for models.
 *
 * This object is light and simple. To avoid accidentally going through reflection. And allows for better caching.
 *
 * @author Nimja
 */
class Reflect {

    /**
     * The database instance.
     * @var \Core\Database
     */
    private $_db;

    /**
     * The name for the databse connection.
     * @var string
     */
    public $dbName;

    /**
     * The table name.
     * @var string
     */
    public $table;

    /**
     * The type name.
     * @var string
     */
    public $type;

    /**
     * The listfield.
     * @var type
     */
    public $listField = null;

    /**
     * Names of all the fields.
     * @var array
     */
    public $fieldNames = array();

    /**
     * The fields + types.
     * @var array
     */
    public $fields = array();

    /**
     * The validation array.
     * @var array
     */
    public $validate = array();

    /**
     * Database column information.
     * @var array
     */
    public $columns = array();

    /**
     * Always get the DB instance dynamically.
     * @return \Core\Database
     */
    public function db()
    {
        if (empty($this->_db)) {
            $this->_db = \Core\Database::getInstance($this->dbName);
        }
        return $this->_db;
    }

    /**
     * Wrapper for callback.
     * @param string $class
     * @return \self
     */
    public static function get($class)
    {
        $reflectClass = new Reflect\Model($class);
        return $reflectClass->getReflect();
    }

}
