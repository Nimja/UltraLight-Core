<?php namespace Core\Controller\Admin\Helper;
/**
 * Helper class for simple editor index, edit and (if possible) order.
 */
class Simple
{
    const MODE_LIST = 'list';
    const MODE_EDIT = 'edit';
    const MODE_ORDER = 'order';
    protected static $_titles = [
        self::MODE_LIST => 'Listing for %s',
        self::MODE_EDIT => 'Editing %s',
        self::MODE_ORDER => 'Changing order for %s',
    ];
    /**
     * Current mode.
     * @var type
     */
    protected $_mode;
    /**
     * The class we are working with, needs to be overridden in subclass.
     *
     * @var string
     */
    protected $_modelClass = '';
    /**
     * Name of the model we're working with, last part of the classname.
     *
     * @var string
     */
    protected $_modelName = '';
    /**
     * Set to true if class has "order" property.
     *
     * @var boolean
     */
    protected $_useOrder = false;
    /**
     * Base link of this page, automatically set if empty.
     *
     * @var string
     */
    protected $_linkBase;
    /**
     * The part of the url for editing/adding a new item.
     *
     * @var string
     */
    protected $_linkEdit;
    /**
     * The part of the url for changing order for an item.
     *
     * @var string
     */
    protected $_linkOrder;
    /**
     * ID from the url.
     * @var type
     */
    protected $_id;

    /**
     * Basic constructor.
     * @param string $class
     * @param string $base
     * @param string $edit
     * @param string $order
     */
    public function __construct($class, $base = '', $edit = 'edit', $order = 'order')
    {
        $this->_modelClass = $class;
        $this->_modelName = substr($class, strrpos($class, '\\') + 1);
        $this->_linkBase = $base ? : '/' . \Core::$route;
        $this->_linkEdit = $edit;
        $this->_linkOrder = $order;
        $this->_useOrder = is_subclass_of($class, \Core\Model\Ordered::class);
        $this->_selectMode();
    }

    /**
     * Get the title for this mode.
     * @return string
     */
    public function getTitle()
    {
        return sprintf(getKey(self::$_titles, $this->_mode, '? %s ?'), $this->_modelName);
    }

    /**
     * Switch between different content modes.
     * @return string
     */
    public function getContent()
    {
        $result = '';
        switch ($this->_mode) {
            case self::MODE_LIST:
                $result = $this->_getListContent();
                break;
            case self::MODE_EDIT:
                $result = $this->_getEditContent();
                break;
            case self::MODE_ORDER:
                $result = $this->_getOrderContent();
                break;
        }
        return $result;
    }

    /**
     * Select the current mode for the output.
     *
     * This is based on the current request.
     *
     * @throws \Exception
     */
    protected function _selectMode()
    {
        $rest = \Core::$rest;
        if (!$rest) {
            $this->_mode = self::MODE_LIST;
        } else {
            $parts = explode('/', $rest);
            $page = array_shift($parts);
            $id = !empty($parts) ? array_shift($parts) : null;
            if ($page == $this->_linkEdit) {
                $this->_mode = self::MODE_EDIT;
            } else if ($page == $this->_linkOrder) {
                if (!$this->_useOrder) {
                    throw new \Exception("Class {$this->_modelClass} does not have order property.");
                }
                $this->_mode = self::MODE_ORDER;
            } else {
                throw new \Exception("Unknown action: $rest");
            }
            $this->_id = intval($id);
        }
    }

    /**
     * Get content for list mode.
     * @result string
     */
    protected function _getListContent()
    {
        $index = new \Core\Model\Tool\Index($this->_modelClass, $this->_linkBase . '/' . $this->_linkEdit);
        $result = '';
        if ($this->_useOrder) {
            $orderUrl = $this->_linkBase . '/' . $this->_linkOrder;
            $result .= "<a href=\"{$orderUrl}\" class=\"btn btn-primary pull-right\">Change order</a><hr />";
        }
        $result .= $index;
        return $result;
    }

    /**
     * Get content for edit mode.
     * @result string
     */
    protected function _getEditContent()
    {
        $edit = new \Core\Model\Tool\Edit(
            $this->_modelClass,
            $this->_linkBase . '/' . $this->_linkEdit,
            $this->_id
        );
        return strval($edit->getForm());
    }

    /**
     * Get content for order mode.
     * @result string
     */
    protected function _getOrderContent()
    {
        $order = new \Core\Model\Tool\Order(
            $this->_modelClass,
            $this->_linkBase . '/' . $this->_linkOrder,
            $this->_id
        );
        return strval($order->getOutput());
    }
}
