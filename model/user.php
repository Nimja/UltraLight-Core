<?php
/**
 * Description of type
 *
 * @author Nimja
 */
class Model_User extends Model_Sessioned
{
    const COOKIE_NAME = 'login_remember';
    const ROLE_BLOCKED = 0;
    const ROLE_NEUTRAL = 1;
    const ROLE_EDITOR = 50;
    const ROLE_ADMIN = 100;
    protected static $_listField = 'username';
    protected static $_fields = array(
        'username' => array(
            'type' => 'varchar',
            'length' => '64',
            'validate' => 'alpha|3',
        ),
        'password' => array(
            'type' => 'varchar',
            'length' => '64',
            'validate' => 'empty|6',
        ),
        'email' => array(
            'type' => 'varchar',
            'length' => '127',
        ),
        'role' => array(
            'type' => 'tinyint',
        ),
    );
    protected $roles = array(
        self::ROLE_NEUTRAL => 'Normal User',
        self::ROLE_EDITOR => 'Editor (can edit, but not delete)',
        self::ROLE_ADMIN => 'Admin (can edit everything)',
    );
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
            Request::setCookie(self::COOKIE_NAME, $cookie, '+2 months');
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
     * Check the current cookie and return a user object if it's vaild.
     * @return null|\self
     */
    public static function checkCookieForRemember()
    {
        $cookie = getKey($_COOKIE, self::COOKIE_NAME);
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
        $db = Library_Database::getDatabase();
        $name = $db->escape($name);
        $pass = $db->escape($pass);
        $table = self::getSetting();
        return $db->fetchFirstValue("SELECT id FROM $table WHERE username=$name AND password=$pass");
    }
    public function saveSession()
    {
        $this->ip = REMOTE_IP;
        parent::saveSession();
    }
}