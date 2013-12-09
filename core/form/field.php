<?php
namespace Core\Form;
/**
 * A model with some automatic 'forms' based on the types.
 */
abstract class Field
{
    /**
     * The fieldname.
     * @var string
     */
    public $name;
    /**
     * Current value.
     * @var string
     */
    public $value;
    /**
     * If this object is an upload form element.
     * @var boolean
     */
    public $isUpload = false;
    /**
     * Flag if this object has an error.
     * @var boolean
     */
    public $hasError = false;
    /**
     * Extra details, like values, style, etc.
     * @var array
     */
    protected $_extra;

    /**
     *
     * @param type $fieldName
     * @param type $extra
     */
    public function __construct($name, $extra = null)
    {
        $this->name = $name;
        if (empty($extra)) {
            $this->_extra = array();
        } else {
            $this->_extra = is_array($extra) ? $extra : array('extra' => $extra);
            $this->_sanitizeExtra(array('value', 'values', 'default'));
        }
        $this->value = getKey($this->_extra, 'value');
    }

    /**
     * Sanitize certain fields in extra.
     * @param string $fields
     */
    protected function _sanitizeExtra($fields)
    {
        foreach ($fields as $field) {
            if (isset($this->_extra[$field])) {
                $this->_extra[$field] = \Sanitize::clean($this->_extra[$field]);
            }
        }
    }

    /**
     * Set the value from the form.
     * @param mixed $value
     */
    public function setValue($value)
    {
        if (!empty($value)) {
            $this->value = $value;
        }
        return $this;
    }

    /**
     * Set the value from the form.
     * @param mixed $value
     */
    public function setHasError($hasError)
    {
        $this->hasError = $hasError;
        return $this;
    }

    /**
     * Add class to extra variable, without overwriting existing classes.
     *
     * @param array $extra details
     * @return string Extra details for html.
     */
    protected function _extra($data)
    {
        $result = '';
        if ($this->hasError) {
            $data = $this->_addClass($data, 'error');
        }
        if (!empty($data)) {
            $parts = array();
            $fields = array('class', 'style', 'id', 'onchange', 'alt', 'title', 'for', 'ref');
            foreach ($fields as $field) {
                if (!empty($data[$field])) {
                    $parts[] = $field . '="' . trim($data[$field]) . '"';
                }
            }
            if (!empty($data['extra'])) {
                $parts[] = trim($data['extra']);
            }
            $result = ' ' . implode(' ', $parts) . ' ';
        }
        return trim($result);
    }

    /**
     * Add class to extra variable, without overwriting existing classes.
     *
     * @param array $extra
     * @param string $class
     * @return the array with adjusted class.
     */
    protected function _addClass($extra, $class)
    {
        if (!is_array($extra)) {
            $extra = array('extra' => $extra);
        }
        $extra['class'] = !empty($extra['class']) ? $extra['class'] . ' ' . $class : $class;
        return $extra;
    }

    abstract protected function _getHtml();

    /**
     * Classes must implement this.
     * @return string
     */
    public function __toString()
    {
        $type = strtolower(array_pop(explode("\\", get_class($this))));
        $result = array('<field class="' . $type . '">');
        if (!empty($this->_extra['label'])) {
            $result[] = '<label><span>' . $this->_extra['label'] . '</span>';
            $result[] = $this->_getHtml();
            $result[] = '</label>';
        } else {
            $result[] = $this->_getHtml();
        }
        $result[] = '<clear /></field>';
        return implode('', $result);
    }
}