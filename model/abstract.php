<?php
/* - This is a simple class-holder for a model, using a DB connection.
 * Containing many useful functions for insert, delete, etc.
 * To understand the DB model; it uses reflection through Model_Reflect_Class.
 */
abstract class Model_Abstract
{
    const ID = 'id';
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
     * Flag for editing.
     *
     * @var boolean
     */
    protected $_edit = false;
    /**
     * Is true if a save succeeded succesfully.
     *
     * @var boolean
     */
    protected $_saved = false;

    /**
     * Basic constructor, with error messages.
     *
     * @param array $values
     */
    public function __construct($values = null)
    {
        $this->_class = get_class($this);
        if (class_exists('Library_Login', false)) {
            $this->_edit = Library_Login::$role > Model_User::ROLE_BLOCKED;
        }
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
            if ($type == 'bool') {
                $this->$field = $value ? 1 : 0;
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
        $class = $this->_class;
        foreach ($this->_re()->fields as $field => $type) {
            $value = getAttr($this, $field);
            if (!blank($value)) {
                $result[$field] = $this->$field;
            }
        }

        $result[self::ID] = $this->id;
        return $result;
    }

    /**
     * Save the current object to the DB, as new or update.
     * @return $this
     */
    public function save()
    {
        $re = $this->_re();
        $db = $re->db;
        $values = $this->getValues();
        //Do we want to do a validation check?
        $id = intval($this->id);
        foreach ($values as $field => $value) {
            if ($re->fields[$field] == self::TYPE_SERIALIZE) {
                $values[$field] = serialize($value);
            }
        }
        $table = $re->table;
        //Switch between update and insert automatically.
        if ($id > 0) {
            $db->update($table, $values, 'id=' . $id);
        } else {
            $this->id = $db->insert($table, $values);
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
        $re->db->delete($table, $this->id);
        self::_clearCache($this->_class, $this);
        $this->id = 0;
    }

    /**
     * Install this model, ie. create the table if it is required.
     *
     * @return type
     */
    public function install($force = false)
    {
        $re = $this->_re();
        $fields = $re->columns;
        if (empty($fields)) {
            return false;
        }
        $table = $re->table;
        $db = $re->db;
        if (!$this->check() || $force) {
            $installed = $db->tableCreate($table, $fields, true);
            #Install default values, if needs be.
            $defaults = $this->getDefaults();
            if (!empty($defaults)) {
                $this->addMultiple($defaults);
            }
            if ($installed) {
                return Show::info($this->_class, 'Installed', 'success', true);
            } else {
                return Show::info($this->_class, 'Not installed!', 'error', true);
            }
        } else {
            $updated = $db->tableUpdate($table, $fields);
            if ($updated) {
                return Show::info($this->_class, 'Updated', 'good', true);
            } else {
                return Show::info($this->_class, 'No changes.', 'neutral', true);
            }
        }
    }

    /**
     * Create multiple items at once with associative arrays.
     *
     * @param array $array
     * @return type
     */
    public function addMultiple($array)
    {
        if (!is_array($array))
            return false;

        #Look over array and add them.
        foreach ($array as $item) {
            if (is_array($item)) {
                $current = new $this->_class($item);
                $current->save();
            }
        }
    }

    /**
     * Return an array of default values.
     * @return array Default values for this class.
     */
    protected function getDefaults()
    {
        $function = 'for' . $this->_class;
        return method_exists('Library_Defaults', $function) ? Library_Defaults::$function() : array();
    }

    /**
     * Check this model, only checking if the table exists.
     *
     * @return boolean Table exists or not.
     */
    protected function check()
    {
        $re = $this->_re();
        return $re->db->table_exists($re->table);
    }

    /**
     * Get the reflect values.
     * @return Model_Reflect_Class
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
        #No validation rules, always true.
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
        return Show::info($this, $this->_class, '#efe', true);
    }
    /* ------------------------------------------------------------
     * 			STATIC FUNCTIONS
     * ------------------------------------------------------------
     */

    /**
     * Get the reflection for this class, like fields, db, columns, etc.
     * @param string $class
     * @return Model_Reflect_Class
     */
    public static function re($class = null)
    {
        $class = $class ? : get_called_class();
        if (empty(self::$_reflections[$class])) {
            self::$_reflections[$class] = new Model_Reflect_Class($class);
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
        if (empty($id)) {
            return null;
        }
        $class = get_called_class();
        $result = self::_loadCache($class, $id);
        if (!$result) {
            $re = self::re($class);
            $values = $re->db->getById($re->table, $id);
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
        $db = $re->db;
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
     * @param Model_Abstract $object
     */
    protected static function _saveCache($class, $object)
    {
        if ($object instanceof Model_Abstract && $object->id > 0) {
            $cacheId = $class . '::' . $object->id;
            self::$_cache[$cacheId] = $object;
        }
    }

    /**
     * Clear object from cache.
     * @param string $class
     * @param Model_Abstract $object
     */
    protected static function _clearCache($class, $object)
    {
        if ($object instanceof Model_Abstract && $object->id > 0) {
            $cacheId = $class . '::' . $object->id;
            unset(self::$_cache[$cacheId]);
        }
    }

    /**
     * Load object from cache.
     * @param string $class
     * @param int $id
     * @return Model_Abstract
     */
    protected static function _loadCache($class, $id)
    {
        $cacheId = $class . '::' . $id;
        return isset(self::$_cache[$cacheId]) ? self::$_cache[$cacheId] : false;
    }
}