<?php
namespace Core\Form\Field;
/**
 * Value field, just pure text and NO post values.
 */
class Value extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $values = $this->_getValues();
        $value = $this->value;
        if ($values) {
            $value = isset($values[$value]) ? $values[$value] : $value;
        }
        return sprintf(
            '<p class="form-control-static" data-name="%s" %s >%s</p>',
            $this->name,
            $this->_extra($this->_extra),
            $value
        );
    }
}