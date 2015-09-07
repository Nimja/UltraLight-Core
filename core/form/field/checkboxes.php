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
        $result = [];
        foreach ($values as $name => $label) {
            $checkBox = new CheckBox($this->name, [CheckBox::EXTRA_BOXVALUE => $name, 'label' => $label]);
            $checkBox->wrapDiv = false;
            $checkBox->setValue($this->value);
            $result[] = $checkBox;
        }
        return implode(PHP_EOL, $result);
    }
}