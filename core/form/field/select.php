<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Select extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $isMultiple = !empty($this->_extra['multiple']);
        $tag = $isMultiple ? ' multiple="multiple"' : '';
        $nameExtra = $isMultiple ? '[]' : '';
        $result = array("<select name=\"{$this->name}{$nameExtra}\"{$tag}>");
        foreach ($this->_getValues() as $key => $value) {
            $selected = $this->_isSelected($isMultiple, $key) ? 'selected="selected"' : '';
            $result[] = "<option value=\"{$key}\" {$selected}>{$value}</option>";
        }
        $result[] = "</select>";
        return implode(PHP_EOL, $result);
    }

    /**
     * Check if value is selected.
     * @param boolean $isMultiple
     * @param mixed $value
     * @return boolean
     */
    protected function _isSelected($isMultiple, $value)
    {
        $result = false;
        if ($isMultiple && is_array($this->value)) {
            $result = in_array($value, $this->value);
        } else {
            $result = $value == $this->value;
        }
        return $result;
    }

    /**
     * Get values from the extra object.
     * @return array
     */
    protected function _getValues()
    {
        $values = getKey($this->_extra, 'values');
        if (empty($values)) {
            $values = array();
        }
        $default = getKey($this->_extra, 'default');
        if (!empty($default)) {
            array_unshift($values, $default);
        }
        return $values;
    }
}