<?php

namespace Core\Model;

/**
 * Description of type
 *
 * @author Nimja
 * @db-database live
 */
class User extends Sessioned
{
    /**
     * User's name (or username if you will). Name they use for login.
     * @listfield
     * @db-type varchar
     * @db-length 64
     * @validate alpha|3
     * @var string
     */
    public $name;
    /**
     * Password.
     * @db-type varchar
     * @db-length 64
     * @validate empty|6
     * @var string
     */
    public $password;
    /**
     * Email.
     * @db-type varchar
     * @db-length 127
     * @var string
     */
    public $email;
    /**
     * Role.
     * @db-type tinyint
     * @var int
     */
    public $role;

    /**
     * User IP address, a limit when not on HTTPS.
     *
     * @var string
     */
    public $ip;

    const ROLE_BLOCKED = 0;
    const ROLE_NEUTRAL = 1;
    const ROLE_EDITOR = 50;
    const ROLE_ADMIN = 100;
    protected $roles = [
        self::ROLE_NEUTRAL => 'Normal User',
        self::ROLE_EDITOR => 'Editor (can edit, but not delete)',
        self::ROLE_ADMIN => 'Admin (can edit everything)',
    ];
    /**
     * The flag if we need to set a cookie.
     * @var boolean
     */
    public $remember = false;
    /**
     * Prevent hash getting generated multiple times.
     * @var array
     */
    private static $_hashes = [];

    /**
     * Return true if role is admin or higher.
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->role >= self::ROLE_ADMIN;
    }

    /**
     * Return true if role is editor or higher.
     * @return boolean
     */
    public function isEditor()
    {
        return $this->role >= self::ROLE_EDITOR;
    }

    /**
     * Set the cookie for this user for remembering.
     * @return $this
     */
    public function setCookie()
    {
        $cookie = $this->makeCookie();
        if (!empty($cookie)) {
            \Request::setCookie($this->_class, $cookie, '+2 months');
        }
        return $this;
    }

    /**
     * Check if the current user has the remember cookie set.
     * @return boolean
     */
    public function hasCookie()
    {
        $cookie = $this->makeCookie();
        $current = \Request::getCookie($this->_class);
        return $current == $cookie;
    }

    /**
     * Make the cookie string (for remembering).
     * @return string
     */
    protected function makeCookie()
    {
        return empty($this->id) ? '' : $this->_getCookieHash();
    }

    /**
     * Get a generated hash based on ID, IP and hashed name.
     *
     * If the connection is over SSL, we skip IP.
     *
     * @return string
     */
    private function _getCookieHash()
    {
        if (!isset(self::$_hashes[$this->id])) {
            $ip = \Request::isSecure() ? 'SSL' : REMOTE_IP;
            self::$_hashes[$this->id] = $this->id . '-' . hash(HASH_TYPE, HASH_KEY . $ip . $this->name);
        }
        return self::$_hashes[$this->id];
    }

    /**
     * Save the current session.
     */
    public function saveSession()
    {
        $this->ip = REMOTE_IP;
        parent::saveSession();
    }

    /**
     * Update and save password.
     * @param string $newPass
     */
    public function updatePassword($newPass)
    {
        $this->password = self::encryptPassword($this->name, $newPass);
        $this->save();
    }

    /**
     * Check the current cookie and return a user object if it's vaild.
     * @return static|null
     */
    public static function loadFromCookie()
    {
        $class = get_called_class();
        $cookie = \Request::getCookie($class);
        $result = null;
        if (!empty($cookie)) {
            $parts = explode('-', $cookie);
            if (count($parts) == 2) {
                $id = $parts[0];
                $check = self::load($id);
                $result = ($cookie != $check->makeCookie()) ? null : $check;
            }
        }
        return $result;
    }

    /**
     * Validate a user/password combination, returning userId.
     *
     * @param string $name
     * @param string $pass
     * @return null|int
     */
    public static function getUserIdForLogin($name, $pass)
    {
        $re = self::re();
        $db = $re->db();
        $search = [
            'name' => $name,
            'password' => self::encryptPassword($name, $pass),
        ];
        $table = $re->table;
        return $db->search($table, $search, ['fields' => self::ID, 'limit' => 1])->fetchFirstValue();
    }

    /**
     * Simple login function.
     * @return static
     */
    public static function login()
    {
        $result = self::loadSession();
        if (empty($result)) {
            $result = self::loadFromCookie();
            if ($result) {
                $result->saveSession();
            }
        }
        // Skip IP check if we are on HTTPS.
        if (
            !empty($result)
            && !\Request::isSecure()
            && !empty($result->ip)
            && $result->ip != REMOTE_IP
        ) {
            \Show::fatal("Logging out?");
            $result = null;
        }
        return $result;
    }

    /**
     * Clear the current session and remove the cookie.
     */
    public static function logout()
    {
        $class = get_called_class();
        \Request::clearCookie($class);
        self::clearSession();
    }

    /**
     * Return encrypted password.
     *
     * @param string $pass
     * @return string
     */
    public static function encryptPassword($user, $pass)
    {
        return hash(HASH_TYPE, HASH_KEY . $pass . strtolower($user));
    }

    /**
     * Return a html form.
     * @param boolean $parsePost
     * @return \Core\Form|null
     */
    public static function formLogin($parsePost = true)
    {
        $class = get_called_class();
        $warning = '';
        $user = null;
        if ($parsePost && \Request::isPost()) {
            try {
                $user = self::attemptLogin(\Request::value('user'), \Request::value('pass'), $class);
                if (\Request::value('remember') && $user) {
                    $user->setCookie();
                }
            } catch (\Exception $e) {
                $warning = $e->getMessage();
            }
        }
        $result = null;
        if ($user) {
            $user->saveSession();
        } else {
            $form = new \Core\Form();
            $form->fieldSet("Login");
            if ($warning) {
                $form->add("<div class=\"warning\">{$warning}</div>");
            }
            $form->add(new \Core\Form\Field\Input('user', ['label' => 'Username']))
                ->add(new \Core\Form\Field\Password('pass', ['label' => 'Password']))
                ->add(new \Core\Form\Field\CheckBox('remember', ['label' => 'Remember me']))
                ->add(new \Core\Form\Field\Submit(null, ['value' => 'Login!', 'class' => 'btn-success']));
            $result = $form;
        }
        return $result;
    }

    /**
     * Attempt login and return user if login is correct.
     * @param string $user
     * @param string $pass
     * @return static|null
     */
    public static function attemptLogin($user, $pass, $class = null)
    {
        $class = $class ?: get_called_class();
        if (empty($user) || empty($pass)) {
            throw new \Exception('Please enter both username and password.');
        }
        $userId = $class::getUserIdForLogin($user, $pass);
        if (empty($userId)) {
            // If a password is incorrect, sleep for a few seconds to slow brute force.
            sleep(rand(5, 10));
            throw new \Exception('Username or password incorrect.');
        }
        return $class::load($userId);
    }
}
