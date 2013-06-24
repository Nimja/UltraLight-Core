<?php
/* - This is a simple class-holder for a model, using a DB connection.
 * Containing many useful functions for insert, delete, etc.
 * This is one of the few libraries using another library as a dependancy.
 * Things needing defining:
 * $_fields array, which defines the table and the used fields.
 */
abstract class Model_Abstract
{
    const ID = 'id';
    const CLASS_PREFIX = 'Model_';
    const SETTING_FIELDS = 'fields';
    const SETTING_TABLE = 'table';
    const SETTING_TYPE = 'type';
    const SETTING_VALIDATE = 'validate';
    const SETTING_IGNORE = 'ignore';
    const SETTING_SERIALIZE = 'serialize';
    /**
     * Settings for each class.
     * @var type
     */
    private static $_classSettings = array();
    /**
     * Cache per execution cycle, to prevent double queries.
     *
     * @var array
     */
    private static $_cache = array();
    /**
     * Field used for ordering and listing.
     *
     * @var string
     */
    protected static $_listField = self::ID;
    /**
     * Database fields
     *
     * @var array
     */
    protected static $_fields = array();
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
     * @var type
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
        foreach ($class::$_fields as $field => $setting) {
            $value = getKey($values, $field, '');
            if ($setting[self::SETTING_TYPE] == 'bool') {
                $this->$field = $value ? 1 : 0;
            } else if (!empty($setting[self::SETTING_SERIALIZE])) {
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
        foreach ($class::$_fields as $field => $type) {
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
        $db = Library_Database::getDatabase();
        $values = $this->getValues();
        //Do we want to do a validation check?
        $id = intval($this->id);
        $ignoreFields = $this->_getSetting(self::SETTING_IGNORE);
        $serializeFields = $this->_getSetting(self::SETTING_SERIALIZE);
        $table = $this->_getSetting();
        //Remove these fields from the values to be saved.
        if (!empty($ignoreFields)) {
            foreach (array_keys($ignoreFields) as $field) {
                unset($values[$field]);
            }
        }
        if (!empty($serializeFields)) {
            foreach (array_keys($serializeFields) as $field) {
                $values[$field] = serialize($values[$field]);
            }
        }
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
        $table = $this->_getSetting();
        Library_Database::getDatabase()->delete($table, $this->id);
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
        $class = $this->_class;
        $fields = $class::$_fields;
        if (empty($fields)) {
            return false;
        }
        $table = $this->_getSetting();
        $db = Library_Database::getDatabase();
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
        $table = $this->_getSetting();
        if (empty($table))
            return false;

        $db = Library_Database::getDatabase();
        return $db->table_exists($table);
    }

    /**
     * Get the setting, from the class. Easier wrapper function.
     * @param string $name
     * @return mixed
     */
    protected function _getSetting($name = self::SETTING_TABLE)
    {
        return self::getSetting($name, $this->_class);
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
     * Get setting for this class.
     *
     * This is only done once per class, per execution cycle.
     *
     * @param string $class
     * @param array $fields
     * @return array
     */
    private static function _getSettings($class)
    {
        if (empty(self::$_classSettings[$class])) {
            $fields = $class::$_fields;
            if (empty($fields) || !is_array($fields)) {
                Show::fatal('Cannot create model unless fields is defined.', $this->_class);
            }
            $result = array();
            //Set the table/name for this setting.
            $result[self::SETTING_TYPE] = str_replace(self::CLASS_PREFIX, '', strtolower($class));
            $prefix = Config::system()->get('database', 'table_prefix', '');
            $result[self::SETTING_TABLE] = str_replace(self::CLASS_PREFIX, $prefix, $class);
            //Create the validation/ignore arrays for ease of use.
            $validate = array();
            $ignore = array();
            $serialize = array();
            foreach ($class::$_fields as $field => $settings) {
                if (isset($settings[self::SETTING_VALIDATE])) {
                    $validate[$field] = $settings['validate'];
                }
                if (!empty($settings[self::SETTING_IGNORE])) {
                    $ignore[$field] = true;
                }
                if (!empty($settings[self::SETTING_SERIALIZE])) {
                    $serialize[$field] = true;
                }
            }
            $result[self::SETTING_VALIDATE] = $validate;
            $result[self::SETTING_IGNORE] = $ignore;
            $result[self::SETTING_SERIALIZE] = $serialize;
            self::$_classSettings[$class] = $result;
        }
        return self::$_classSettings[$class];
    }

    /**
     * Get specific setting.
     * @param type $class
     * @param type $setting
     * @return type
     */
    public static function getSetting($setting = self::SETTING_TABLE, $class = null)
    {
        $class = $class ?: get_called_class();
        $settings = self::_getSettings($class);
        return getKey($settings, $setting);
    }

    /**
     * Load an object, with ID. This will return a cached object if present.
     * @param int $id
     * @return /self
     */
    public static function load($id)
    {
        $class = get_called_class();
        $result = self::_loadCache($class, $id);
        if (!$result) {
            $table = self::getSetting(self::SETTING_TABLE, $class);
            $db = Library_Database::getDatabase();
            $values = $db->getById($table, $id);
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
        $table = self::getSetting(self::SETTING_TABLE, $class);
        $db = Library_Database::getDatabase();
        $order = $order ?: "{$class::$_listField} ASC";
        $values = $db->searchTable($table, $search, $order, 1)->fetchRow();
        if (!empty($values)) {
            $result = new $class($values);
            self::_saveCache($class, $result);
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
        $table = self::getSetting(self::SETTING_TABLE, $class);
        $db = Library_Database::getDatabase();
        $order = $order ?: "{$class::$_listField} ASC";
        $res = $db->searchTable($table, $search, $order, $limit)->last;
        $result = array();
        while ($row = $db->fetchRow($res)) {
            $model = new $class($row);
            $result[$model->id] = $model;
        }
        return $result;
    }

    /**
     * Get the fields for this class.
     * @return type
     */
    protected static function fields()
    {
        $class = get_called_class();
        return $class::$_fields;
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