<?php namespace Core;
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
    protected $_contentType = 'text/html; charset=UTF-8';
    /**
     * The required role, if any.
     * @var int
     */
    protected $_requiredRole = 0;
    /**
     * The user class, is required if setting role.
     * @var int
     */
    protected $_userClass = \Core\Model\User::class;
    /**
     * Instance of user.
     * @var \Core\Model\User
     */
    protected $_user = null;
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
        if (!\Core::$console && $this->_requiredRole > 0 && !empty($this->_userClass)) {
            $class = $this->_userClass;
            $user = $class::login();
            $role = !empty($user) ? $user->role : 0;
            if ($role < $this->_requiredRole && \Core::$url != $this->_loginRedirect) {
                $session = new Session();
                $session->set('login.url', \Core::$url);
                \Request::redirect($this->_loginRedirect, \Request::STATUS_REDIRECT_SEE_OTHER);
            } else {
                $this->_user = $user;
            }
        }
    }

    /**
     * The function actually running the page.
     *
     * If any output has been sent (like echo/print_r, etc.) rather than returned we do NOT send type headers.
     *
     * If we're running in console mode, we don't capture output.
     */
    final public function display()
    {
        if (\Core::$console) {
            echo $this->_executeRun();
        } else {
            ob_start();
            $result = $this->_executeRun();
            $output = ob_get_flush();
            if (!headers_sent() && empty($output)) {
                header('Content-Type: ' . $this->_contentType);
            }
            if (class_exists(\Core\View::class, false)) {
                $result = \Core\View::unescape($result);
            }
            echo $result;
        }
    }

    /**
     * Executing the run function, so it can be overwritten in child classes.
     *
     * @return string
     */
    protected function _executeRun()
    {
        return $this->_run();
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
    protected function _show($page, $variables = [], $parseText = false)
    {
        $result = $this->_view()->show($page, $variables);
        if ($parseText) {
            $result = \Core\Format\Text::parse($result);
        }
        return $result;
    }

    /**
     * Show error page.
     * @param int $code
     * @param string $view
     * @return array|string
     */
    protected function _showError($code = 0, $view = '')
    {
        $message = getKey($this->_getErrorMessages(), $code);
        if (empty($message)) {
            $message = 'Unknown - An unknown error occured.';
            $code = \Request::STATUS_SERVER_ERROR;
        }
        list($title, $content) = explode(' - ', $message);
        $result = [
            'title' => $title,
            'content' => $content,
        ];
        if ($view) {
            $result = $this->_show($view, $result);
        }
        http_response_code($code);
        return $result;
    }
    /**
     * Get array of error messages, they are split on the space dash space for title and content.
     * @return array
     */
    protected function _getErrorMessages()
    {
        return [
            \Request::STATUS_ERROR_FORBIDDEN => 'Forbidden - You are not allowed to view this page.',
            \Request::STATUS_ERROR_NOT_FOUND => 'Not found - The page you requested cannot be found.',
        ];
    }

    /**
     * Create an instance, can only be done once.
     * @return static
     */
    public static function create()
    {
        $class = get_called_class();
        return new $class();
    }
}