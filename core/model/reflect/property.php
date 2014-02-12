<?php
namespace Core\Model\Reflect;
/**
 * Property reflection for models.
 *
 * @author Nimja
 */
class Property
{
    const PROPERTY_DB = 'db-';
    /**
     * The current property.
     * @var ReflectionProperty
     */
    private $_property;
    /**
     * The current docblock;.
     * @var array;
     */
    private $_doc;

    /**
     * Basic constructor.
     * @param ReflectionProperty $property
     */
    public function __construct($property)
    {
        $this->_property = $property;
        $this->_doc = Model::parseDocComment($property->getDocComment());
    }

    /**
     * Get the field information and set it in the array.
     * @param \Core\Model\Reflect $class
     */
    public function fillSettings($class)
    {
        if ($this->_isColumn()) {
            $field = $this->getName();
            $doc = $this->_doc;
            if (!empty($doc['validate'])) {
                $class->validate[$field] = $doc['validate'];
            }
            $class->columns[$field] = $this->_getDbFields();
            $class->fields[$field] = $this->_getFieldType();
            if (isset($doc['listField'])) {
                $class->listField = $field;
            }
        }
    }

    /**
     * Get the name of the current property.
     * @return string
     */
    public function getName()
    {
        return $this->_property->getName();
    }

    /**
     * Is this property a DB field.
     * @return boolean
     */
    private function _isColumn()
    {
        return isset($this->_doc[self::PROPERTY_DB . 'type']);
    }

    /**
     * Get the column information.
     * @return array
     */
    private function _getDbFields()
    {

        $result = array();
        foreach ($this->_doc as $key => $value) {
            if (substr($key, 0, 3) != 'db-') {
                continue;
            }
            $result[substr($key, 3)] = $value;
        }
        return $result;
    }

    /**
     * Get field type (boolean, int, etc.) as a string.
     * @return string
     */
    private function _getFieldType()
    {
        $result = getKey($this->_doc, 'db-type', 'int');
        if (isset($this->_doc[\Core\Model::TYPE_SERIALIZE])) {
            $result = \Core\Model::TYPE_SERIALIZE;
        }
        return $result;
    }
}