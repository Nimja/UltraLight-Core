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
        $class = getKey($this->_extra, 'class');
        foreach ($values as $name => $label) {
            $extra = [CheckBox::EXTRA_BOXVALUE => $name, 'label' => $label, 'class' => $class];
            $checkBox = new CheckBox($this->name, $extra);
            $checkBox->wrapDiv = false;
            $checkBox->setValue($this->value);
            $result[] = $checkBox;
        }
        return implode(PHP_EOL, $result);
    }
}