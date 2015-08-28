<?php
namespace Core;
/**
 * Beautifully simple form creator.
 *
 * The output is compatible with Bootstrap.
 */
class Form
{
    /**
     * If we are in a fieldset.
     * @var string
     */
    private $_inFieldSet = false;
    /**
     * Data, one for every "add".
     * @var array
     */
    private $_data = [];
    /**
     * The basic settings.
     * @var string
     */
    private $_formTag;
    /**
     * The values in the current form.
     * @var array
     */
    private $_values;
    /**
     * Form contains upload field.
     * @var boolean
     */
    private $_containsUpload = false;

    /**
     * If form is inline.
     * @var boolean
     */
    private $_isHorizontal = false;

    /**
     * Change the fieldset.
     * @param string $page
     * @param null|array $extra
     * @param string $method
     * @return $this
     */
    public function __construct($page = null, $extra = null, $method = 'post')
    {
        $page = !empty($page) ? $page : '/' . \Core::$url;
        if ($extra && isset($extra['class']) && strpos($extra['class'], 'form-horizontal') !== null) {
            $this->_isHorizontal = true;
        }
        $this->_formTag = sprintf('<form action="%s" method="%s" %s role="form">', $page, $method, $this->_extra($extra));
        $this->_values = \Request::getValues();
    }

    /**
     * Change the fieldset.
     * @param type $legend
     * @param null|array $extra
     * @return \Core\Form
     */
    public function fieldSet($legend = null, $extra = null)
    {
        $this->fieldSetClose();
        $this->add("<fieldset {$this->_extra($extra)}>");
        if ($legend) {
            $this->add("<legend>{$legend}</legend>");
        }
        $this->_inFieldSet = true;
        return $this;
    }

    /**
     * Close currently open fieldset.
     * @return \Core\Form
     */
    public function fieldSetClose()
    {
        if ($this->_inFieldSet) {
            $this->add('</fieldset>');
        }
        $this->_inFieldSet = false;
        return $this;
    }

    /**
     * Add field.
     * @param string|\Core\Form\Field $field
     * @return \Core\Form
     */
    public function add($field)
    {
        $this->_addAtIndex(-1, $field);
        return $this;
    }


    /**
     * Attempt to add before an existing field in the form.
     * @param string $name
     * @param string|\Core\Form\Field $field
     * @return \Core\Form
     */
    public function addBefore($name, $field)
    {
        return $this->_addAtIndex($this->_getIndex($name), $field);
    }
    /**
     * Attempt to add after an existing field in the form.
     * @param string $name
     * @param string|\Core\Form\Field $field
     * @return \Core\Form
     */
    public function addAfter($name, $field)
    {
        $index = $this->_getIndex($name);
        if ($index > -1) {
            $index++;
        }
        return $this->_addAtIndex($index, $field);
    }

    /**
     * Simple function to find the index for a field.
     *
     * This of course only works for instances of \Core\Form\Field.
     * @param string $name
     * @return int
     */
    protected function _getIndex($name)
    {
        $result = -1;
        $index = 0;
        foreach ($this->_data as $item) {
            if ($item instanceof \Core\Form\Field && $item->name == $name) {
                $result = $index;
                break;
            }
            $index++;
        }
        return $result;
    }

    /**
     * Add item at index.
     * @param int $index
     * @param string|\Core\Form\Field $field
     * @return \Core\Form
     */
    protected function _addAtIndex($index, $field)
    {
        $fieldData = $this->_configureField($field);
        if ($index < 0 || $index >= count($this->_data)) {
            $this->_data[] = $fieldData;
        } else {
            array_splice($this->_data, $index, 0, [$fieldData]);
        }
        return $this;
    }


    /**
     * Configure field.
     * @param string|\Core\Form\Field $field
     * @return \Core\Form\Field
     */
    private function _configureField($field)
    {
        if ($field instanceof \Core\Form\Field) {
            $field->setValue($this->getValue($field->name));
            $field->setHorizontal($this->_isHorizontal);
            if ($field->isUpload) {
                $this->_containsUpload = true;
            }
        }
        return $field;
    }

    /**
     * Set warnings and add them to the top of the form.
     *
     * This is the only function that 'bypasses' the add functionality.
     * @param array $warnings
     * @return \Core\Form
     */
    public function setWarnings($warnings)
    {
        $result = [];
        if (!empty($warnings)) {
            $result[] = '<div class="alert alert-danger">';
            foreach ($warnings as $field => $message) {
                $field = ucfirst($field);
                $result[] = "<p><b>{$field}</b> {$message}</p>";
            }
            $result[] = '</div>';
        }
        array_unshift($this->_data, implode(PHP_EOL, $result));
        return $this;
    }

    /**
     * Add class to extra variable, without overwriting existing classes.
     *
     * @param array $extra details
     * @param string $field
     * @return string Extra details for html.
     */
    private function _extra($data)
    {
        $result = '';
        if (!empty($data)) {
            if (!is_array($data)) {
                $result = ' ' . trim($data) . ' ';
            } else {
                $parts = [];
                $fields = ['class', 'style', 'id', 'onchange', 'alt', 'title', 'for', 'ref'];
                foreach ($fields as $field) {
                    if (!empty($data[$field])) {
                        $parts[] = $field . '="' . trim($data[$field]) . '"';
                    }
                }
                if (!empty($data['extra'])) {
                    $parts[] = trim($data['extra']);
                }
                $result = ' ' . implode(' ', $parts) . ' ';
            }
        }
        return $result;
    }

    /**
     * Add default values.
     * @param array $values
     * @param boolean $force
     * @return \Core\Form
     */
    public function useValues($values, $force = false)
    {
        if (!empty($values) && is_array($values)) {
            $values = \Sanitize::clean($values);
            foreach ($values as $key => $value) {
                $cur = getKey($this->_values, $key, $value);
                $this->_values[$key] = $force ? $value : $cur;
            }
        }
        return $this;
    }

    /**
     * Get value of field.
     * @param string $field
     * @return mixed
     */
    public function getValue($field)
    {
        return getKey($this->_values, $field);
    }

    /**
     * Very simple toString function.
     * @return string
     */
    public function __toString()
    {
        $this->fieldSetClose();
        $tag = $this->_formTag;
        if ($this->_containsUpload) {
            $tag = trim(substr($tag, 0, -1)) . ' enctype="multipart/form-data">';
        }
        $result = array_merge([$tag], $this->_data);
        $result[] = '</form>';
        return implode(PHP_EOL, $result);
    }
}