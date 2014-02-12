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
    const COOKIE_NAME = 'login_remember';
    /**
     * Username.
     * @listfield
     * @db-type varchar
     * @db-length 64
     * @validate alpha|3
     * @var string
     */
    public $username;
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
    const ROLE_BLOCKED = 0;
    const ROLE_NEUTRAL = 1;
    const ROLE_EDITOR = 50;
    const ROLE_ADMIN = 100;
    protected $roles = array(
        self::ROLE_NEUTRAL => 'Normal User',
        self::ROLE_EDITOR => 'Editor (can edit, but not delete)',
        self::ROLE_ADMIN => 'Admin (can edit everything)',
    );
    /**
     * The flag if we need to set a cookie.
     * @var boolean
     */
    public $remember = false;
    /**
     * Prevent hash getting generated multiple times.
     * @var array
     */
    private static $_hashes = array();

    /**
     * Set the cookie for this user for remembering.
     * @return \self
     */
    public function setCookie()
    {
        $cookie = $this->makeCookie();
        if (!empty($cookie)) {
            \Request::setCookie(self::COOKIE_NAME, $cookie, '+2 months');
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
        $current = getKey($_COOKIE, self::COOKIE_NAME);
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
     * Get a generated hash based on ID, IP and hashed username.
     * @return type
     */
    private function _getCookieHash()
    {
        if (!isset(self::$_hashes[$this->id])) {
            self::$_hashes[$this->id] = $this->id . '-' . hash(HASH_TYPE, HASH_KEY . REMOTE_IP . $this->username);
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
     * Check the current cookie and return a user object if it's vaild.
     * @return null|\self
     */
    public static function loadFromCookie()
    {
        $cookie = \Request::getCookie(self::COOKIE_NAME);
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
        $name = $db->escape($name);
        $pass = $db->escape($pass);
        $table = $re->table;
        return $db->fetchFirstValue("SELECT id FROM $table WHERE username=$name AND password=$pass");
    }

    /**
     * Simple login function.
     * @return Model_User
     */
    public static function login()
    {
        $result = self::loadSession();
        if (empty($result)) {
            $result = self::loadFromCookie();
        }
        if (!empty($result) && !empty($result->ip) && $result->ip != REMOTE_IP) {
            $result = null;
        }
        return $result;
    }

    /**
     * Clear the current session and remove the cookie.
     */
    public static function logout()
    {
        \Request::clearCookie(self::COOKIE_NAME);
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
        return hash(HASH_TYPE, HASH_KEY . $pass . $user);
    }
    /**
     * Return a html form.
     * @param boolean $parsePost
     * @return string
     */
    public static function formLogin($parsePost = true)
    {

    }
}