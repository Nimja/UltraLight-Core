<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class CheckBoxes extends \Core\Form\Field
{
    protected $_isMultiple = true;
    protected $_labelClass = 'div';
    protected function _getHtml()
    {
        $values = $this->_getValues();
        $result = array();
        foreach ($values as $name => $label) {
            $checked = $this->_isSelected($name) ? 'checked="checked"' : '';
            $result[] = "<label><l>$label</l><input type=\"checkbox\" name=\"{$this->name}[$name]\" {$checked}/></label>";
        }
        return implode(PHP_EOL, $result);
    }
}