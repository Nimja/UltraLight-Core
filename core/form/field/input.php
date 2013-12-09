<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Input extends \Core\Form\Field
{

    protected function _getHtml()
    {
        return sprintf(
            '<input type="text" name="%s" value="%s" %s />',
            $this->name,
            $this->value,
            $this->_extra($this->_extra)
        );
    }
}