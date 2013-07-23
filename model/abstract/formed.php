<?php
/**
 * A model with some automatic 'forms' based on the types.
 */
abstract class Model_Abstract_Formed extends Model_Abstract
{
    /**
     * Holder for the form object connected to this function.
     *
     * @var Library_Form
     */
    protected $_form = null;

    /**
     * Get a basic array list for this object, for use in dropdowns.
     *
     * @param string $where Basic SQL where structure.
     * @param string $orderBy And order by.
     * @return array Field structure.
     */
    public function getSelectionList($where = '', $orderBy = null)
    {
        $class = $this->_class;
        $re = $this->_re();
        $db = $re->db;
        $table = $db->escape($re->table);
        $listField = $re->listField;
        $orderBy = !empty($orderBy) ? $orderBy : "$listField ASC";
        $extra = '';
        if (!empty($where)) {
            $extra .= ' WHERE ' . $where;
        }
        if (!empty($orderBy)) {
            $extra .= ' ORDER BY ' . $orderBy;
        }
        return $db->getList('id', $listField, "SELECT * FROM  $table $extra");
    }

    /**
     * Return an HTML form to edit this object.
     *
     * @param string $page	Submit page.
     * @param string $method post or get
     * @param array $extra
     * @return string Form HTML output.
     */
    public function getForm($page = '', $method = 'post', $extra = array(), $selector = true)
    {
        if (empty($this->_form)) {
            $this->_form = new Library_Form();
        }
        #Begin the form.
        $class = $this->_class;
        $fields = $this->_re()->fields;
        $type = $this->setting(self::SETTING_TYPE);

        $form = $this->_form;

        $form->add_class($extra, 'editor');
        $form->begin($page, $method, $extra);

        #Set the original values.
        $form->orivalues = $this->values();

        #Basic fields.
        $form->field('hidden', 'class', null,
            array(
            'value' => $type,
            'ref' => $this->_saved ? 'saved' : '',
        ));
        $form->field('hidden', 'id', null, array(
            'value' => $this->id,
        ));

        if (!empty($form->warning)) {
            $form->add('<div class="warning">' . $form->warning . '</div>');
        }

        #Current object selection.
        if ($selector) {
            $list = array('' => '--new--') + $this->getSelectionList();
            $form->field('select', 'id', 'Current', array('values' => $list, 'class' => 'idSelect'));
        }

        $form->fieldset('Values', array('class' => 'values ' . strtolower($this->_class)));
        foreach ($fields as $field => $setting) {
            $this->getFormField($form, $field, $setting);
        }
        $form->field('submit', 'submit', null, array('value' => 'Save', 'class' => 'save'));
        $form->field('submit', 'cancel', null, array('value' => 'Cancel', 'class' => 'cancel'));

        #Execute extra details for a form.
        $this->getFormExtra($form);

        $form->end();

        return $form->output;
    }

    /**
     * Create (or ignore) a field for this form.
     *
     * @param Library_Form $form
     * @param string $field
     * @param array $setting
     */
    public function getFormField($form, $field, $setting)
    {
        #Ignore these fields for editing.
        if (!empty($this->_ignoreFields[$field]))
            return;

        # We only need the type, not anything else.
        $type = $setting['type'];
        $lastChars = substr($field, -2);

        $ttype = substr($type, -3);

        switch ($ttype) {
            #Text types.
            case 'ext':
                $form->field('text', $field, ucfirst($field));
                break;

            #Bool
            case 'ool':
                $form->field('check', $field, '&nbsp;', array('label' => ucfirst($field)));
                break;
            #Numeric, id or date types
            case 'int':
                if ($lastChars == 'On') {
                    $form->orivalues[$field] = date('d-m-Y H:i:s', $this->$field);
                    $form->field('input', $field, ucfirst($field), array('class' => 'dateField'));
                } else if ($lastChars == 'Id') {
                    $fieldClass = ucfirst(substr($field, 0, -2));
                    #If we're looking for parents, using current class.
                    if ($fieldClass == 'Parent') {
                        $fieldClass = $this->_class;
                    }
                    $list = self::make($fieldClass);
                    $ref = $fieldClass . '-' . intval(getAttr($this, $field));
                    $extra = array('values' => $list->getSelectionList(), 'lead' => '<span class="edit" ref="' . $ref . '"></span>');
                    $form->field('select', $field, ucfirst(substr($field, 0, -2)), $extra);
                } else {
                    $form->field('input', $field, ucfirst($field), array('class' => 'numberField'));
                }

                break;
            default: $form->field('input', $field, ucfirst($field), array('class' => $type));
        }
    }

    /**
     * Do additional stuff for a form.
     */
    protected function getFormExtra()
    {
        //Placeholder function.
    }

    public function editTag($class = '', $parentId = 0)
    {
        if (!$this->_edit)
            return '';

        $thisClass = strtolower($this->_class);

        if (!empty($class) && !empty($parentId)) {
            return '<span class="edit edit_new" ref="' . $class . '--' . $parentId . '">New ' . ucfirst($class) . '</span>';
        } else {
            return '<span class="edit" ref="' . $thisClass . '-' . $this->id . '"></span>';
        }
    }

    /**
     *
     * @param type $alias
     * @param string $alt Alternative for alias (like a title)
     * @return string Properly formatted alias.
     */
    protected function validAlias($alias, $alt)
    {
        #Clean Alias.
        if (blank($alias)) {
            $alias = preg_replace('/\s+/', ' ', trim($alt));
        }
        #Clean the alias.
        $alias = strtolower(trim($alias));
        $alias = str_replace(' ', '_', $alias);
        $alias = preg_replace('/[^a-z0-9\-\_\/\.]/', '', $alias);
        return $alias;
    }
}