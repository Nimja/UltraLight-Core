<?php

namespace Core\Model;

/**
 * Class reflection for models.
 *
 * This object is light and simple. To avoid accidentally going through reflection. And allows for better caching.
 *
 * Functionality is in the owner object.
 * @see \Core\Model\Reflect\Model
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
    public $fieldNames = [];

    /**
     * The fields + types.
     * @var array
     */
    public $fields = [];

    /**
     * Fields allowed to be blank.
     * @var array
     */
    public $blankFields = [];

    /**
     * The validation array.
     * @var array
     */
    public $validate = [];

    /**
     * Database column information.
     * @var array
     */
    public $columns = [];

    /**
     * Lazy/late loaded properties.
     * @var array
     */
    public $lazy = [];

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
