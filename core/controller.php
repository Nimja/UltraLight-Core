<?php
namespace Core;
/* This is a simple controller class, containing basic functions for
 * AJAX, GET and POST variables and a few other useful things.
 */
abstract class Controller
{
    /**
     * The view.
     * @var \Core\View
     */
    private $_view;
    /**
     * The returned content type.
     * @var string
     */
    protected $_contentType = 'text/html';
    /**
     * The required role, if any.
     * @var int
     */
    protected $_requiredRole = 0;
    /**
     * The required role, if any.
     * @var int
     */
    protected $_userClass;
    /**
     * Login redirect page if required role is not met.
     * @var string
     */
    protected $_loginRedirect = 'admin/login';

    /**
     * Basic constructor, switch between several display types.
     */
    private function __construct()
    {
        if ($this->_requiredRole > 0 && !empty($this->_userClass)) {
            $class = $this->_userClass;
            $user = $class::login();
            $role = !empty($user) ? $user->role : 0;
            if ($role < $this->_requiredRole) {
                \Request::redirect($this->_loginRedirect, 303);
            }
        }
    }

    /**
     * The function actually running the page.
     */
    final public function display()
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $this->_contentType);
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
            $result = Show::error($e, "Exception!", true);
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
     * @return \Core\View
     */
    protected function _view()
    {
        if (!$this->_view) {
            $this->_view = \Core\View::getInstance();
        }
        return $this->_view;
    }

    /**
     * Quick access to the view->show function.
     * @param string $page
     * @param array $variables
     * @param boolean $parseText
     * @return string
     */
    protected function _show($page, $variables = array(), $parseText = false)
    {
        $result = $this->_view()->show($page, $variables);
        if ($parseText) {
            $result = \Core\Format\Text::parse($result);
        }
        return $result;
    }

    /**
     * Create an instance, can only be done once.
     * @return \self
     */
    public static function create() {
        $class = get_called_class();
        return new $class();
    }
}