<?php

namespace Core;

/* - This is a simple class-holder for a model, using a DB connection.
 * Containing many useful functions for insert, delete, etc.
 * To understand the DB model; it uses reflection through Model_Reflect_Class.
 */

abstract class Model {

    const DATE_FORMAT_SHORT = 'Y-m-d';
    const DATE_FORMAT_LONG = 'Y-m-d H:i:s';
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
     * Fill the object with values.
     *
     * Properties in the entity that are not set in the array, become blank unless $add is true.
     *
     * @var array $values
     * @var array $add If true, we add values and leave alone missing values from the array.
     * @return array All the values of this object.
     */
    public function setValues($values, $add = false)
    {
        if (empty($values) || !is_array($values)) {
            return false;
        }
        $class = $this->_class;
        foreach ($this->_re()->fields as $field => $type) {
            $value = getKey($values, $field, $add ? $this->$field : '');
            $this->_setValue($field, $type, $value);
        }
        if (!empty($values[self::ID]) && !$add) {
            $this->id = intval($values[self::ID]);
            self::_saveCache($class, $this);
        }
    }

    /**
     * Set value.
     * @param string $field
     * @param string $type
     * @param mixed $value
     */
    protected function _setValue($field, $type, $value) {
        if ($type == self::TYPE_BOOL) {
            $this->$field = $value ? true : false;
        } else if ($type == self::TYPE_SERIALIZE) {
            $this->$field = is_string($value) ? unserialize($value) : $value;
        } else {
            $this->$field = $value;
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
     * @param Model\Reflect $re
     * @return array
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
        $this->_saveValues($values);
        self::_saveCache($this->_class, $this);
        return $this;
    }

    /**
     * Save values, allowing to update only a single value.
     * @param array $values
     * @return $this
     */
    protected function _saveValues($values)
    {
        $re = $this->_re();
        $db = $re->db();
        $id = intval($this->id);
        //Switch between update and insert automatically.
        if ($id > 0) {
            $db->update($re->table, $values, $id);
        } else {
            $this->id = $db->insert($re->table, $values);
        }
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
     * Simple generic to string method.
     * @return type
     */
    public function __toString()
    {
        return \Show::info($this, $this->_class, \Show::COLOR_NICE, true);
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
     * Load an object, with ID. This will return a cached object if present.
     * @param int $id
     * @return /self|null
     */
    public static function load($id)
    {
        $id = intval($id);
        if (empty($id)) {
            return null;
        }
        $class = get_called_class();
        $result = self::_loadCache($class, $id);
        if (!$result) {
            $re = self::re($class);
            $values = $re->db()
                ->search($re->table, $id)
                ->fetchFirstRow();
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
        $settings = array(
            'order' => $order,
            'limit' => $limit,
        );
        $res = $db->search($re->table, $search, $settings)->getRes();
        $result = array();
        while ($row = $res->fetch_assoc()) {
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
