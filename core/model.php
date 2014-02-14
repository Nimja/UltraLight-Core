<?php

namespace Core;

/* - This is a simple class-holder for a model, using a DB connection.
 * Containing many useful functions for insert, delete, etc.
 * To understand the DB model; it uses reflection through Model_Reflect_Class.
 */

abstract class Model {

    const ID = 'id';
    const TYPE_BOOL = 'bool';
    const TYPE_SERIALIZE = 'serialize';

    /**
     * Reflection for each class, used for fields and columns.
     * @var array
     */
    private static $_reflections = array();

    /**
     * Cache per execution cycle, to prevent double queries.
     *
     * @var array
     */
    private static $_cache = array();

    /**
     * Static memory caching.
     *
     * @var array
     */
    protected static $cache = array();

    /**
     * The current class.
     *
     * @var string
     */
    protected $_class;

    /**
     * ID variable
     *
     * @var int
     */
    public $id = 0;

    /**
     * Is true if a save succeeded succesfully.
     *
     * @var boolean
     */
    protected $_saved = false;

    /**
     * Maintain relationships.
     *
     * @var array
     */
    protected $_related = array();

    /**
     * Basic constructor, with error messages.
     *
     * @param array $values
     */
    public function __construct($values = null)
    {
        $this->_class = get_class($this);
        if (is_array($values)) {
            $this->setValues($values);
        }
    }

    /**
     * Fill the object, empty means everything is blank.
     *
     * @var array $values
     * @return array All the values of this object.
     */
    public function setValues($values)
    {
        if (empty($values) || !is_array($values)) {
            return false;
        }
        $class = $this->_class;
        foreach ($this->_re()->fields as $field => $type) {
            $value = getKey($values, $field, '');
            if ($type == self::TYPE_BOOL) {
                $this->$field = $value ? true : false;
            } else if ($type == self::TYPE_SERIALIZE) {
                $this->$field = is_string($value) ? unserialize($value) : $value;
            } else {
                $this->$field = $value;
            }
        }
        if (!empty($values[self::ID])) {
            $this->id = intval($values[self::ID]);
            self::_saveCache($class, $this);
        }
    }

    /**
     * Retrieve all the values as a associative array.
     *
     * @return array All the values of this object.
     */
    public function getValues()
    {
        $result = array();
        foreach ($this->_re()->fieldNames as $field) {
            $value = getAttr($this, $field);
            if (!blank($value)) {
                $result[$field] = $this->$field;
            }
        }
        if (!empty($this->id)) {
            $result[self::ID] = $this->id;
        }
        return $result;
    }

    /**
     * Get values that are used as saving. Easy to overwrite.
     * @param
     * @return type
     */
    protected function _getValuesForSave($re)
    {
        $values = $this->getValues();
        foreach ($re->fields as $field => $type) {
            if (!isset($values[$field])) {
                continue;
            } else if ($type == self::TYPE_BOOL) {
                $values[$field] = $values[$field] ? true : false;
            } else if ($type == self::TYPE_SERIALIZE) {
                $values[$field] = serialize($values[$field]);
            }
        }
        return $values;
    }

    /**
     * Save the current object to the DB, as new or update.
     * @return $this
     */
    public function save()
    {
        $re = $this->_re();
        $db = $re->db();
        $values = $this->_getValuesForSave($re);
        if (empty($values)) {
            throw new \Exception("Attempting to save empty model.");
        }
        $id = intval($this->id);
        //Switch between update and insert automatically.
        if ($id > 0) {
            $db->update($re->table, $values, $id);
        } else {
            $this->id = $db->insert($re->table, $values);
        }
        self::_saveCache($this->_class, $this);
        return $this;
    }

    /**
     * Delete this object (or specific id)
     *
     * @param optional int $id
     */
    public function delete()
    {
        $re = $this->_re();
        $table = $re->table;
        $re->db()->delete($table, $this->id);
        self::_clearCache($this->_class, $this);
        $this->id = 0;
    }

    /**
     * Get the reflect values.
     * @return \Core\Model\Reflect
     */
    protected function _re()
    {
        return self::re($this->_class);
    }

    /**
     * Validate the object, using the validation rules.
     *
     * @return array Returns empty array on success, or array with warnings on failure.
     */
    public function validate($exclude = array())
    {
        $result = array();
        // No validation rules, always true.
        if (!empty($this->_validate)) {
            $validateRules = $this->_validate;
            if (!empty($exclude)) {
                foreach ($exclude as $field) {
                    unset($validateRules[$field]);
                }
            }
            $validator = new Library_Validate();
            $validator->validate($this->values(), $validateRules);
            $result = $validator->warnings;
        }
        return $result;
    }

    /**
     * Get a related entity.
     * @param string $field
     * @param string $class
     * @return \Core\Model
     */
    protected function _getRelated($field, $class)
    {
        if (empty($this->$field) || !class_exists($class)) {
            throw new \Exception("$field empty or not present.");
        }
        if (empty($this->_related[$class])) {
            $entity = $class::load($this->$field);
            if (empty($entity)) {
                throw new \Exception("No $class with id {$this->$field}.");
            }
            $this->_related[$class] = $entity;
        }
        return $this->_related[$class];
    }

    /**
     * Simple generic to string method.
     * @return type
     */
    public function __toString()
    {
        return \Show::info($this, $this->_class, '#efe', true);
    }

    /* ------------------------------------------------------------
     * 			STATIC FUNCTIONS
     * ------------------------------------------------------------
     */

    /**
     * Get the reflection for this class, like fields, db, columns, etc.
     * @param string $class
     * @return \Core\Model\Reflect
     */
    public static function re($class = null)
    {
        $class = $class ? : get_called_class();
        if (empty(self::$_reflections[$class])) {
            $time = filemtime(\Core::$classes[$class]['file']);
            self::$_reflections[$class] = \Core::wrapCache('\Core\Model\Reflect::get', array($class), $time);
        }
        return self::$_reflections[$class];
    }

    /**
     * Install this model, ie. create the table if it is required.
     *
     * @return void
     */
    public static function install($force = false)
    {
        $class = get_called_class();
        $re = $class::re();
        $fields = $re->columns;
        if (empty($fields)) {
            return false;
        }
        $table = $re->db()->table($re->table);
        $result = $table->applyStructure($fields, $force);
        switch ($result) {
            case \Core\Database\Table::STRUCTURE_UPDATED:
                \Show::info("$class", 'Table updated.');
                break;
            case \Core\Database\Table::STRUCTURE_CREATED:
                \Show::info("$class", 'Table created.');
                $count = self::addMultiple($class::getDefaults(), $class);
                if ($count > 0) {
                    \Show::info("$class","Inserted $count rows.");
                }
                break;
            default:
                \Show::info("$class", 'No actions.');
        }
    }

    /**
     * Return an array of default values.
     * @return array Default values for this class.
     */
    protected static function getDefaults()
    {
        return null;
    }

    /**
     * Load an object, with ID. This will return a cached object if present.
     * @param int $id
     * @return /self|null
     */
    public static function load($id)
    {
        if (empty($id)) {
            return null;
        }
        $class = get_called_class();
        $result = self::_loadCache($class, $id);
        if (!$result) {
            $re = self::re($class);
            $values = $re->db()->getById($re->table, $id);
            if (!empty($values)) {
                $result = new $class($values);
                self::_saveCache($class, $result);
            }
        }
        return $result;
    }

    /**
     * Find a single objectby search.
     * @param string $search Like id|=4
     * @param string $order
     * @return /self
     */
    public static function findOne($search, $order = null)
    {
        $result = null;
        $class = get_called_class();
        $rows = $class::find($search, $order, 1);
        if (!empty($rows)) {
            $result = array_shift($rows);
        }
        return $result;
    }

    /**
     * Search through the database for the right object.
     *
     * This uses simple search, like id|=5;
     * @param string $search
     * @param string $order
     * @param string $limit
     * @return /Collection_Abstract
     */
    public static function find($search = null, $order = null, $limit = null)
    {
        $class = get_called_class();
        $re = self::re($class);
        $db = $re->db();
        $listField = $re->listField;
        $order = $order ? : "{$listField} ASC";
        $res = $db->searchTable($re->table, $search, $order, $limit)->last;
        $result = array();
        while ($row = $db->fetchRow($res)) {
            $model = new $class($row);
            $result[$model->id] = $model;
            self::_saveCache($class, $model);
        }
        return $result;
    }

    /**
     * Save object to cache.
     * @param string $class
     * @param \Core\Model $object
     */
    protected static function _saveCache($class, $object)
    {
        if ($object instanceof \Core\Model && $object->id > 0) {
            $cacheId = $class . '::' . $object->id;
            self::$_cache[$cacheId] = $object;
        }
    }

    /**
     * Clear object from cache.
     * @param string $class
     * @param \Core\Model $object
     */
    protected static function _clearCache($class, $object)
    {
        if ($object instanceof \Core\Model && $object->id > 0) {
            $cacheId = $class . '::' . $object->id;
            unset(self::$_cache[$cacheId]);
        }
    }

    /**
     * Load object from cache.
     * @param string $class
     * @param int $id
     * @return \Core\Model
     */
    protected static function _loadCache($class, $id)
    {
        $cacheId = $class . '::' . $id;
        return isset(self::$_cache[$cacheId]) ? self::$_cache[$cacheId] : false;
    }

    /**
     * Create multiple items at once with associative arrays.
     *
     * @param array $array
     * @return void
     */
    public static function addMultiple($array, $class = null)
    {
        $class = $class ? : get_called_class();
        if (!is_array($array)) {
            return false;
        }
        // Loop over array and add them.
        $count = 0;
        foreach ($array as $item) {
            if (is_array($item)) {
                $current = new $class($item);
                /* @var $current \Core\Model */
                $current->save();
                $count++;
            }
        }
        return $count;
    }

}
