<?php

Library_Login::init();

/**
 * Class for login procedures.
 */
class Library_Login
{

    const SUCCESS = 'success';
    const ROLE_NEUTRAL = 0;
    const ROLE_EDITOR = 1;
    const ROLE_ADMIN = 2;
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const USER_ID = 'user_id';
    const USER_IP = 'user_ip';
    const CLASS_MODEL = 'User';

    /**
     *
     * @var User
     */
    public static $user = null;

    /**
     * The current user role
     * @var int
     */
    public static $role = 0;

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

        $user = Model::make(self::CLASS_MODEL, $id);
        /* @var $user User */
        if (empty($user->id)) {
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
        unset($_SESSION[self::USER_ID], $_SESSION[self::USER_IP]);
        Core::clearCookie(Model_User::COOKIE_NAME);
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
        $title = $loggedin ? '' . self::$user : 'login';
        $content = '';
        if ($loggedin) {
            $content .= '<a class="button" href="?action=' . self::ACTION_LOGOUT . '">logout</a>';
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
                if (empty($user) || empty($pass)) {
                    $warning = 'Fill in both fields.';
                } else {
                    $pass = self::encryptPassword($user, $pass);
                    $userobj = new Model_User();
                    $id = $userobj->validate($user, $pass);
                    if (empty($id)) {
                        $warning = 'Unknown username/password...';
                    } else {
                        $user = Model::make(self::CLASS_MODEL, $id);
                        self::doLogin($user, $remember);
                        return self::SUCCESS;
                    }
                }
            }
            if (!empty($warning))
                $content .= '<div class="warning">' . $warning . '</div>';
            $content .= $form->output;
        }
        $data = array(
            'title' => $title,
            'content' => $content,
        );
        return Library_View::show('login', $data);
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
    public static function setUser($name, $pass, $role = self::ROLE_NEUTRAL)
    {
        $pass = self::encryptPassword($name, $pass);
        $user = Model::make(self::CLASS_MODEL);
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

