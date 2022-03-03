<?php

namespace Core\Model\Tool;

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
     * The current id, is allowed to be empty.
     * @var int
     */
    protected $_id;
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
     * @param int $id
     */
    public function __construct($class, $editLink, $id = null)
    {
        $this->_entityClass = $class;
        $this->_editLink = $editLink;
        $this->_id = $id;
    }

    /**
     * Set form explicitly for a custom form.
     * @param \Core\Form $form
     * @throws \Exception
     */
    public function setForm($form)
    {
        if (!$form instanceof \Core\Form && $form !== null) {
            throw new \Exception("Not setting form correctly.");
        }
        $this->_form = $form;
    }

    /**
     * Get entity, returns entity OR redirects if we are successfully editing.
     * @return \Core\Model
     */
    public function getEntity()
    {
        $class = $this->_entityClass;
        $entity = $class::load($this->_id);
        /* @var $entity \Core\Model */
        if (!empty($this->_id) && empty($entity)) {
            \Request::redirect($this->_editLink);
        }
        $entity = $entity ?: new $class();
        $values = $class::getForm($entity)->getValues();
        if (!empty($values) && \Request::isPost()) {
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
