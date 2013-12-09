<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class CheckBox extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $checked = !empty($this->value) ? 'checked="checked"' : '';
        return sprintf(
            '<input type="checkbox" name="%s" %s %s/>',
            $this->name,
            $checked,
            $this->_extra($this->_extra)
        );
    }
}