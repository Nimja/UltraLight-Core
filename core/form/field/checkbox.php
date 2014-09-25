<?php namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class CheckBox extends \Core\Form\Field
{
    /**
     * Label for this checkbox.
     * @var type
     */
    protected $_label = '';

    public function __construct($name, $extra = null)
    {
        parent::__construct($name, $extra);
        if (isset($this->_extra['label'])) {
            $this->_label = ' ' . $this->_extra['label'];
            unset($this->_extra['label']);
        }
    }

    protected function _getHtml()
    {
        $checked = !empty($this->value) ? 'checked="checked"' : '';
        return sprintf(
            '<label><input type="checkbox" name="%s" %s %s/>%s</label>',
            $this->name,
            $checked,
            $this->_extra($this->_extra),
            $this->_label
        );
    }
}