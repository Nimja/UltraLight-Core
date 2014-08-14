<?php

namespace Core\Model\Reflect;

/**
 * Class reflection for models.
 *
 * @author Nimja
 */
class Model {

    const DOCCOMMENT_REGEX = '/@([a-zA-Z-]+)(.*)/';

    /**
     * The current class.
     * @var ReflectionClass
     */
    private $_class;

    /**
     * The result object.
     * @var \Core\Model\Reflect
     */
    private $_reflect;

    /**
     * @param string $class
     */
    public function __construct($class)
    {
        if (!is_subclass_of($class, '\Core\Model')) {
            throw new \Exception("Reflecting non model class: $class");
        }
        $this->_reflect = new \Core\Model\Reflect();
        $short = str_replace('\\', '_', $class);
        $this->_setTableName($short);
        $this->_reflect->type = strtolower($short);
        $this->_class = new \ReflectionClass($class);
        $this->_setDbName();
        $this->_getSettings();
        unset($this->_class);
    }

    /**
     * Return the filled reflect object.
     *
     * This object is light and simple. To avoid accidentally going through reflection.
     * @return \Core\Model\Reflect
     */
    public function getReflect()
    {
        return $this->_reflect;
    }

    private function _setDbName()
    {
        $doc = self::parseDocComment($this->_class->getDocComment());
        $dbName = null;
        if (!empty($doc)) {
            $dbName = getKey($doc, 'db-database');
        }
        $this->_reflect->dbName = $dbName;
    }

    /**
     * Get the settings information.
     */
    private function _getSettings()
    {
        $properties = $this->_class->getProperties();
        foreach ($properties as $property) {
            $prop = new Property($property);
            $prop->fillSettings($this->_reflect);
        }
        if (empty($this->_reflect->listField)) {
            $keys = array_keys($this->_reflect->fields);
            $this->_reflect->listField = array_shift($keys);
        }
        $this->_reflect->fieldNames = array_keys($this->_reflect->fields);
    }

    /**
     * Get the table name.
     * @param string $shortName
     * @return string
     */
    private function _setTableName($shortName)
    {
        $prefix = \Config::system()->get('database', 'table_prefix', '');
        $this->_reflect->table = $prefix . $shortName;
    }

    /**
     * Parse the doc comment into a nice array.
     * @param string $docComment
     * @return array
     */
    public static function parseDocComment($docComment)
    {
        $result = array();
        if (!empty($docComment)) {
            $matches = null;
            if (preg_match_all(self::DOCCOMMENT_REGEX, $docComment, $matches)) {
                $fields = $matches[1];
                $values = $matches[2];
                foreach ($fields as $key => $value) {
                    $var = trim(getKey($values, $key, ''));
                    $result[$value] = $var == '' ? true : $var;
                }
            }
        }
        return $result;
    }

    /**
     * Wrapper for callback.
     * @param string $class
     * @return \self
     */
    public static function get($class)
    {
        return new self($class);
    }

}
