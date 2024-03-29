<?php

namespace Core\Form\Field;

/**
 * Checkbox for forms.
 *
 * If you want to use a checkbox in a group, use "boxvalue" to set the value of this specific checkbox.
 */
class CheckBox extends \Core\Form\Field
{
    const EXTRA_BOXVALUE = 'boxvalue';
    /**
     * Label for this checkbox.
     * @var string
     */
    protected $_label = '';

    public function __construct($name, $extra = null, $params = null)
    {
        parent::__construct($name, $extra, $params);
        if (isset($this->_extra['label'])) {
            $this->_label = ' ' . $this->_extra['label'];
            unset($this->_extra['label']);
        }
        $this->_isMultiple = !empty($extra[self::EXTRA_BOXVALUE]);
    }

    protected function _getHtml()
    {
        $name = $this->name;
        if ($this->_isMultiple) {
            $value = $this->_extra[self::EXTRA_BOXVALUE];
            unset($this->_extra[self::EXTRA_BOXVALUE]);
            $checked = $this->_isSelected($value) ? 'checked="checked"' : '';
            $checked .= " value=\"{$value}\"";
            $name .= '[]';
        } else {
            $checked = !empty($this->value) ? 'checked="checked"' : '';
        }
        return sprintf(
            '<label class="checkbox-inline"><input type="checkbox" name="%s" %s %s/>%s</label>',
            $name,
            $checked,
            $this->_extra($this->_extra),
            $this->_label
        );
    }
}
