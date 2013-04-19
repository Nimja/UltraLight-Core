<?php
Library_Login::init();
/**
 * Class for login procedures.
 */
class Library_Login
{
    const SUCCESS = 'success';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const USER_ID = 'user_id';
    const USER_IP = 'user_ip';
    /**
     *
     * @var Model_User
     */
    public static $user = null;
    /**
     * The current user role
     * @var int
     */
    public static $role = 0;
    /**
     * Class for the user object.
     * @var type
     */
    protected static $_userClass = 'Model_User';

    /**
     * Initialize the logged in user.
     */
    public static function init()
    {
        $id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $ip = !empty($_SESSION['user_ip']) ? $_SESSION['user_ip'] : false;

        if (!empty($ip) && $ip != REMOTE_IP) {
            self::logout();
            $id = 0;
            $ip = 0;
        }

        $class = self::$_userClass;
        $user = $class::load($id);
        /* @var $user User */
        if (empty($user)) {
            $user = new $class();
            $user = $user->checkCookie();
        } else if (!empty($_SESSION['remember'])) {
            $user->setCookie();
        }
        self::doLogin($user);
    }

    /**
     * Very simple logout function.
     */
    public static function logout()
    {
        $class = self::$_userClass;
        unset($_SESSION[self::USER_ID], $_SESSION[self::USER_IP]);
        Core::clearCookie($class::COOKIE_NAME);
        self::$user = null;
        self::$role = 0;
    }

    /**
     * Login function.
     * @param User $user
     */
    private static function doLogin($user, $remember = false)
    {
        if (empty($user) || empty($user->id)) {
            self::logout();
            return;
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_ip'] = REMOTE_IP;
        $_SESSION['remember'] = $remember;
        self::$user = $user;
        self::$role = $user->role;
    }

    /**
     *
     * @return string The login box.
     */
    public static function loginBox($parse = true)
    {
        $loggedin = !empty(self::$user);
        $result = '';
        if ($loggedin) {
            $result = self::_getLoginView(self::$user, '<a class="button" href="' . self::ACTION_LOGOUT . '">logout</a>');
        } else {
            $form = new Library_Form();
            $form->begin('?', 'post', array('id' => 'login_form'));
            $form->field('input', 'user', 'Username');
            $form->field('password', 'pass', 'Password');
            $form->field('check', 'remember', '&nbsp;', array('label' => 'Remember me'));
            $form->field('submit', 'submit', null, array('value' => 'login'));
            $form->end();

            $user = $form->value('user', null, false);
            $pass = $form->value('pass', null, false);
            $remember = $form->value('remember', null, false);

            $warning = '';
            if ($parse && $_SERVER['REQUEST_METHOD'] == 'POST') {
                $id = self::validate($user, $pass);
                if (empty($user) || empty($pass)) {
                    $warning = 'Fill in both fields.';
                } else if (!$id) {
                    $warning = 'Unknown username/password...';
                }
                if (empty($warning)) {
                    $class = self::$_userClass;
                    $user = $class::load($id);
                    self::doLogin($user, $remember);
                    $result = self::SUCCESS;
                } else {
                    $result = self::_getLoginView('Login', self::_getLoginForm($form, $warning));
                }
            } else {
                $result = self::_getLoginView('Login', self::_getLoginForm($form));
            }
        }
        return $result;
    }

    private static function _getLoginView($title, $content)
    {
        return Library_View::getInstance()->show('login',
                array(
                'title' => $title,
                'content' => $content,
        ));
    }

    /**
     * Get login form contents.
     * @param Library_Form $form
     * @param string $warning
     * @return string
     */
    private static function _getLoginForm($form, $warning = '')
    {
        $result = '';
        if (!empty($warning)) {
            $result .= '<div class="warning">' . $warning . '</div>';
        }
        $result .= $form->output;
        return $result;
    }

    /**
     * Validate user/pass.
     * @param type $user
     * @param type $pass
     * @return boolean
     */
    public static function validate($user, $pass)
    {
        $pass = self::encryptPassword($user, $pass);
        $class = self::$_userClass;
        return $class::getUserIdForLogin($user, $pass);
    }

    /**
     * Return encrypted password.
     *
     * @param string $pass
     * @return string
     */
    public static function encryptPassword($user, $pass)
    {
        return hash(HASH_TYPE, HASH_KEY . $pass . $user);
    }

    /**
     * Make a new user or update an existing user.
     *
     * @param type $user
     * @param type $pass
     */
    public static function setUser($name, $pass, $role = null)
    {
        $class = self::$_userClass;
        $role = $role ?: $class::ROLE_NEUTRAL;
        $pass = self::encryptPassword($name, $pass);
        $user = new $class();
        $existing = $user->getOne('name = "' . $name . '"');
        if (!empty($existing)) {
            $user = $existing;
        }
        $user->name = $name;
        $user->password = $pass;
        $user->role = $role;
        $user->save();
    }
}
