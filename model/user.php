<?php
/**
 * Description of type
 *
 * @author Nimja
 */
class Model_User extends Model_Formed
{
    const COOKIE_NAME = 'remember';
    const ROLE_BLOCKED = 0;
    const ROLE_NEUTRAL = 0;
    const ROLE_EDITOR = 1;
    const ROLE_ADMIN = 2;
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

    public function getFormField($field, $setting)
    {
        $form = $this->_form;
        if ($field == 'role') {
            if (Library_Login::$role == self::ROLE_ADMIN)
                $form->field('select', $field, 'Role', array('values' => $this->roles));
        } else if ($field == 'password') {
            $form->field('password', $field, 'Password', array('value' => ''));
        } else {
            parent::getFormField($field, $setting);
        }
    }

    /**
     * Set the cookie for this user for remembering.
     * @return \self
     */
    public function setCookie()
    {
        $cookie = $this->makeCookie();
        if (!empty($cookie)) {
            Core::setCookie(self::COOKIE_NAME, $cookie, '+2 months');
        }
        return $this;
    }

    /**
     * Make the cookie string (for remembering).
     * @return string
     */
    protected function makeCookie()
    {
        $result = '';
        if (!empty($this->id)) {
            $result = $this->id . '-' . hash(HASH_TYPE, HASH_KEY . REMOTE_IP . $this->username);
        }
        return $result;
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

    /**
     * Return username.
     * @return type
     */
    public function __toString()
    {
        $result = '';
        if (!empty($this->username)) {
            $this->_edit = Library_Login::$role > self::ROLE_EDITOR || Library_Login::$user->id == $this->id;
            $result = $this->editTag() . $this->username;
        }
        return $result;
    }
}