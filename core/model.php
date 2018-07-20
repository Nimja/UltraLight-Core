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
    const TYPE_ARRAY = 'array';

    /**
     * Reflection for each class, used for fields and columns.
     * @var array
     */
    private static $_reflections = [];

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
     * Is true if a save succeeded successfully.
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
        if (is_array($values)) {
            $this->setValues($values);
            if (!empty($values[self::ID])) {
                $this->id = intval($values[self::ID]);
            }
        }
    }

    /**
     * Fill the object with values.
     *
     * We only set values that are present in the array.
     * If the value is empty/null, this is only allowed for certain fields.
     *
     * @var array $values
     * @return array All the values of this object.
     */
    public function setValues($values)
    {
        if (empty($values) || !is_array($values)) {
            return false;
        }
        $re = $this->_re();
        foreach ($values as $field => $value) {
            if (!isset($re->fields[$field])) {
                continue;
            }
            if (empty($value) && !isset($re->blankFields[$field])) {
                continue;
            }
            $this->_setValue($field, $re->fields[$field], $value);
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
        } else if ($type == self::TYPE_ARRAY) {
            if (empty($value)) {
                $this->$field = [];
            } else {
                $this->$field = is_string($value) ? explode(',', $value) : $value;
            }
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
        $result = [];
        $re = $this->_re();
        foreach ($re->fieldNames as $field) {
            $value = getAttr($this, $field);
            if (!blank($value) || isset($re->blankFields[$field])) {
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
            } else if ($type == self::TYPE_ARRAY) {
                $values[$field] = implode(',', $values[$field]);
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
        $values = $this->_getValuesForSave($re);
        if (empty($values)) {
            throw new \Exception("Attempting to save empty model.");
        }
        $this->_saveValues($values);
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
     * If there are no validation rules, obviously it is valid.
     *
     * @return array Returns empty array on success, or array with warnings on failure.
     */
    public function validate($exclude = [])
    {
        $result = [];
        $validateRules = $this->_re()->validate;
        if (!empty($validateRules)) {
            if (!empty($exclude)) {
                foreach ($exclude as $field) {
                    unset($validateRules[$field]);
                }
            }
            $validator = new \Core\Form\Validate();
            $validator->validate($this->getValues(), $validateRules);
            $result = $validator->warnings;
        }
        return $result;
    }

    /**
     * Simple generic to string method.
     * @return string
     */
    public function getString()
    {
        return \Show::info($this, $this->_class, \Show::COLOR_NICE, true);
    }
    /**
     * Magic tostring method.
     */
    public function __toString()
    {
        try {
            $result = $this->getString();
        } catch (\Exception $ex) {
            $result = \Show::output($ex, "Exception!", \Show::COLOR_ERROR);
        }
        return $result;
    }

    /**
     * Getter for lazy loaded properties. Will throw exception if not set.
     *
     * Set lazy loaded property with @property-read \Class $property
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $class = getKey($this->re()->lazy, $name);
        if (!$class) {
            throw new \Exception("Attempting to lazy load unconfigured property {$name}");
        }
        $result = $class::load($this->{$name . 'Id'});
        $this->{$name} = $result;
        return $result;
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
            if (!isset(\Core::$classes[$class])) {
                $lowerClass = strtolower($class);
                foreach (array_keys(\Core::$classes) as $className) {
                    if (strtolower($className) == $lowerClass) {
                        \Show::fatal(
                            ['called ' => $className, 'defined' => $class], "Capitalisation error, check spelling!"
                        );
                    }
                }
                \Show::fatal("$class not found. Not even in other capitalisation.");
            }
            $time = filemtime(\Core::$classes[$class]['file']);
            self::$_reflections[$class] = \Core::wrapCache('\Core\Model\Reflect::get', [$class], $time);
        }
        return self::$_reflections[$class];
    }

    /**
     * Load an object, with ID. This will return a cached object if present.
     * @param int $id
     * @return \Core\Model|null
     */
    public static function load($id)
    {
        $id = intval($id);
        if (empty($id)) {
            return null;
        }
        $class = get_called_class();
        $re = self::re($class);
        $result = null;
        $values = $re->db()
            ->search($re->table, $id)
            ->fetchFirstRow();
        if (!empty($values)) {
            $result = new $class($values);
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
     * @return array
     */
    public static function find($search = null, $order = null, $limit = null)
    {
        $class = get_called_class();
        $re = self::re($class);
        $db = $re->db();
        $listField = $re->listField;
        $order = $order ? : "{$listField} ASC";
        $settings = [
            'order' => $order,
            'limit' => $limit,
        ];
        $res = $db->search($re->table, $search, $settings)->getRes();
        $result = [];
        while ($row = $res->fetch_assoc()) {
            $model = new $class($row);
            $result[$model->id] = $model;
        }
        return $result;
    }

    /**
     * Create multiple items at once with associative arrays.
     *
     * @param array $items
     * @return void
     */
    public static function addMultiple($items)
    {
        $class = get_called_class();
        if (!is_array($items)) {
            return false;
        }
        // Loop over array and add them.
        $count = 0;
        foreach ($items as $item) {
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
