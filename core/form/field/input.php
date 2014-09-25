<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Input extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        $type = getKey($this->_extra, 'type', 'text');
        return sprintf(
            '<input type="%s" name="%s" value="%s" %s />',
            $type,
            $this->name,
            $this->value,
            $this->_extra($this->_extra)
        );
    }
}