<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Text extends \Core\Form\Field
{

    protected function _getHtml()
    {
        return sprintf(
            '<textarea name="%s" %s>%s</textarea>',
            $this->name,
            $this->_extra($this->_extra),
            $this->value
        );
    }
}