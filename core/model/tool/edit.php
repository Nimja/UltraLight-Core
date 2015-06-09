<?php namespace Core\Model\Tool;
/**
 * Helper entity for page model, but can be used more broad.
 *
 * @author Nimja
 */
class Edit
{
    /**
     * The class we use.
     * @var string
     */
    protected $_entityClass;
    /**
     * The edit link.
     * @var string
     */
    protected $_editLink;
    /**
     * The form we will use.
     * @var \Core\Form
     */
    protected $_form;
    /**
     * Warnings after validation.
     * @var array
     */
    public $warnings;

    /**
     *
     * @param string $class
     * @param string $editLink
     * @param \Core\Form $form
     */
    public function __construct($class, $editLink, $form = null)
    {
        $this->_entityClass = $class;
        $this->_editLink = $editLink;
        $this->_form = $form;
    }

    /**
     * Get entity, returns entity OR redirects if we are successfully editing.
     * @return \Core\Model
     */
    public function getEntity()
    {
        $class = $this->_entityClass;
        $values = \Request::getValues();
        $warnings = array();
        $entity = $class::load(\Core::$rest);
        /* @var $entity \Core\Model */
        if (!empty(\Core::$rest) && empty($entity)) {
            \Request::redirect($this->_editLink);
        }
        if (!empty($values)) {
            $entity = $entity ? : new $class();
            $entity->setValues($values);
            $this->warnings = $entity->validate();
            if (empty($this->warnings)) {
                $entity->save();
                \Request::redirect($this->_editLink . '/' . $entity->id);
            }
        }
        return $entity;
    }

    /**
     * Will return edit form or redirect after saving.
     * @return \Core\Form
     */
    public function getForm()
    {
        $entity = $this->getEntity();
        if (!$this->_form) {
            $class = $this->_entityClass;
            $this->_form = $class::getForm($entity);
        }
        $this->_form->setWarnings($this->warnings);
        return $this->_form;
    }
}
