<?php namespace Core\Form;
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
     * @var string|array
     */
    public $value;
    /**
     * Flag if this object has an error.
     * @var boolean
     */
    public $hasError = false;
    /**
     * Wrap this in a div.
     *
     * @var boolean
     */
    public $wrapDiv = true;
    /**
     * Extra details, like values, style, etc.
     * @var array
     */
    protected $_extra;
    /**
     * If this is a multiple
     * @var boolean
     */
    protected $_isMultiple = false;
    /**
     * The class in which we wrap the label.
     * @var type
     */
    protected $_labelClass = 'label';
    /**
     * If the field is horizontal (label + input on one line).
     * @var boolean
     */
    protected $_isHorizontal = false;

    /**
     * Construct with name and additional values.
     * @param string $name
     * @param array $extra
     */
    public function __construct($name, $extra = null)
    {
        $this->name = $name;
        if (empty($extra)) {
            $this->_extra = [];
        } else {
            $this->_extra = is_array($extra) ? $extra : array('extra' => $extra);
            $this->_sanitizeExtra(['value', 'values', 'default']);
            $this->_isMultiple = $this->_isMultiple || !empty($this->_extra['multiple']);
        }
        $this->_setValue(getKey($this->_extra, 'value'));
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
        if (!blank($value)) {
            $this->_setValue($value);
        }
        return $this;
    }

    /**
     * Encode + because of variables.
     * @param string $value
     */
    protected function _setValue($value)
    {
        $this->value = \Core\View::escape($value);
    }

    /**
     * Set if we render as horizontal.
     * @param boolean $isHorizontal
     */
    public function setHorizontal($isHorizontal)
    {
        $this->_isHorizontal = $isHorizontal;
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
            $ignore = [
                'type' => true,
                'multiple' => true,
                'default' => true,
                'label' => true,
                'extra' => true,
                'value' => true,
                'values' => true,
            ];
            $parts = [];
            foreach ($data as $field => $value) {
                if (isset($ignore[$field])) {
                    continue;
                }
                if (!empty($data[$field])) {
                    $parts[] = $field . '="' . trim($value) . '"';
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
            $extra = ['extra' => $extra];
        }
        $extra['class'] = !empty($extra['class']) ? $extra['class'] . ' ' . $class : $class;
        return $extra;
    }

    abstract protected function _getHtml();

    /**
     * Get multiple values from the extra object (for select, checkboxes, etc.).
     * @return array
     */
    protected function _getValues()
    {
        $values = getKey($this->_extra, 'values');
        if (empty($values)) {
            $values = [];
        }
        $default = getKey($this->_extra, 'default');
        if (!empty($default)) {
            $result = ['' => $default];
            foreach ($values as $key => $value) {
                $result[$key] = $value;
            }
        } else {
            $result = $values;
        }
        return $result;
    }

    /**
     * Check if value is selected.
     * @param string $value
     * @return boolean
     */
    protected function _isSelected($value)
    {
        $result = false;
        if ($this->_isMultiple && is_array($this->value)) {
            $result = in_array($value, $this->value);
        } else {
            $result = $value == $this->value;
        }
        return $result;
    }

    /**
     * Classes must implement this.
     * @return string
     */
    public function __toString()
    {
        $result = [];
        if ($this->wrapDiv) {
            $result[] = "<div class=\"form-group\">";
        }
        if (!empty($this->_extra['label'])) {
            $result[] = $this->_isHorizontal ? $this->_getHorizontalLabel() : $this->_getNormalLabel();
        } else {
            $result[] = $this->_isHorizontal ? $this->_getHorizontalHtml() : $this->_getHtml();
        }
        if ($this->wrapDiv) {
            $result[] = '</div>';
        }
        return implode('', $result);
    }

    /**
     * Get with label for horizontal display.
     * @return string
     */
    protected function _getHorizontalLabel()
    {
        $result = "<{$this->_labelClass} class=\"col-sm-2 control-label\">{$this->_extra['label']}</{$this->_labelClass}>";
        $result .= '<div class="col-sm-10">';
        $result .= $this->_getHtml();
        $result .= '</div>';
        return $result;
    }

    /**
     * Get with label for normal display.
     * @return string
     */
    protected function _getNormalLabel()
    {
        $result = "<{$this->_labelClass} for=\"{$this->name}\">{$this->_extra['label']}</{$this->_labelClass}>";
        $result .= $this->_getHtml();
        return $result;
    }

    /**
     * Wrap offset around html/
     * @return string
     */
    protected function _getHorizontalHtml()
    {
        if (!$this->wrapDiv) {
            return $this->_getHtml();
        }
        return '<div class="col-sm-offset-2 col-sm-10">' . $this->_getHtml() . '</div>';
    }
}