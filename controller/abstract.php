<?php
/* This is a simple controller class, containing basic functions for
 * AJAX, GET and POST variables and a few other useful things.
 */
abstract class Controller_Abstract
{
    const VAR_GET = 'GET';
    const VAR_POST = 'POST';
    /**
     *
     * @var Library_View
     */
    private $_view;
    /**
     *
     * @var type
     */
    protected $_requiredRole = 0;
    /**
     *
     * @var type
     */
    protected $_loginRedirect = 'admin/login';

    /**
     * Basic constructor, switch between several display types.
     */
    private function __construct()
    {
        if ($this->_requiredRole > 0) {
            if (Library_Login::$role < $this->_requiredRole) {
                Core::redirect($this->_loginRedirect, 303);
            }
        }
        echo $this->_executeRun();
    }

    /**
     * Executing the run function, so it can be overwritten in child classes.
     * @return type
     */
    protected function _executeRun() {
        try {
            $result = $this->_run();
        } catch (Exception $e) {
            $result = Show::error($error, "Exception!", true);
        }
        return $result;
    }

    /**
     * The run function for the extended controller.
     *
     * @return string
     */
    abstract protected function _run();

    /**
     * Get the current view instance.
     */
    protected function _view()
    {
        if (!$this->_view) {
            $this->_view = Library_View::getInstance();
        }
        return $this->_view;
    }

    /**
     * Quick access to the view->show function.
     * @param string $page
     * @param array $variables
     * @return string
     */
    protected function _show($page, $variables = array())
    {
        return $this->_view()->show($page, $variables);
    }

    /**
     * Create an instance, can only be done once.
     * @return \self
     */
    public static function load() {
        $class = get_called_class();
        return new $class();
    }
}