<?php namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Submit extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $value = getKey($this->_extra, 'value', "Submit");
        return sprintf(
            '<button type="submit" %s>%s</button>', $this->_extra($this->_extra), $value
        );
    }
}