<?php namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class CheckBoxGroup extends \Core\Form\Field
{
    /**
     * Label for this checkbox.
     * @var type
     */
    protected $_label = '';
    protected $_isMultiple = true;

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
        $sub = getKey($this->_extra, 'sub', 'sub');
        unset($this->_extra['sub']);
        $checked = $checked = $this->_isSelected($sub) ? 'checked="checked"' : '';
        return sprintf(
            '<label><input type="checkbox" name="%s[]" value="%s" %s %s/>%s</label>',
            $this->name,
            $sub,
            $checked,
            $this->_extra($this->_extra),
            $this->_label
        );
    }
}