<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Radio extends \Core\Form\Field
{
    protected $_labelClass = 'div';
    protected function _getHtml()
    {
        $values = $this->_getValues();
        $result = [];
        foreach ($values as $name => $label) {
            $checked = $this->_isSelected($name) ? 'checked="checked"' : '';
            $radioInput = "<input type=\"radio\" name=\"{$this->name}\" value=\"$name\" {$checked}/>";
            $result[] = "<label class=\"radio-inline\">{$radioInput}{$label}</label>";
        }
        return implode(PHP_EOL, $result);
    }
}