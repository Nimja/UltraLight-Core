<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Password extends \Core\Form\Field
{

    protected function _getHtml()
    {
        return sprintf(
            '<input type="password" name="%s" value="" %s />',
            $this->name,
            $this->_extra($this->_extra)
        );
    }
}