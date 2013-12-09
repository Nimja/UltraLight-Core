<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Submit extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $value = getKey($this->_extra, 'value', "Submit");
        return sprintf(
            '<input type="submit" name="%s" value="%s" %s />',
            $this->name,
            $value,
            $this->_extra($this->_extra)
        );
    }
}