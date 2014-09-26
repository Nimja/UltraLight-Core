<?php namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Submit extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'btn btn-block');
        $value = getKey($this->_extra, 'value', "Submit");
        return sprintf(
            '<button type="submit" %s>%s</button>', $this->_extra($this->_extra), $value
        );
    }
}