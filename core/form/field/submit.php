<?php

namespace Core\Form\Field;

/**
 * A model with some automatic 'forms' based on the types.
 */
class Submit extends \Core\Form\Field
{
    /**
     * The button class.
     *
     * @var string
     */
    public $buttonClass = 'btn btn-block';

    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, $this->buttonClass);
        $value = getKey($this->_extra, 'value', "Submit");
        return sprintf(
            '<button type="submit" %s>%s</button>',
            $this->_extra($this->_extra),
            $value
        );
    }
}
