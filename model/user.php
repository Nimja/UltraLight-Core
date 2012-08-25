<?php

/**
 * Description of type
 *
 * @author Nimja
 */
class Model_User extends Model
{

    const COOKIE_NAME = 'remember';
    const ROLE_NEUTRAL = 0;
    const ROLE_EDITOR = 1;
    const ROLE_ADMIN = 2;

    protected $_listField = 'name';
    protected $_fields = array(
        'name' => array(
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
        'cookie' => array(
            'type' => 'varchar',
            'length' => '64',
            'ignore' => true,
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

    public function setCookie()
    {
        $cookie = $this->makeCookie();
        if (empty($cookie))
            return false;

        $this->cookie = $cookie;
        $this->getDb()->update($this->_table, array('cookie' => $cookie), 'id = ' . $this->id);

        //Cookie is valid for 2 months.
        Core::setCookie(self::COOKIE_NAME, $cookie, '+2 months');
    }

    /**
     * Make the cookie string.
     * @return string 
     */
    protected function makeCookie()
    {
        if (empty($this->id))
            return FALSE;

        return $this->id . '-' . hash(HASH_TYPE, HASH_KEY . REMOTE_IP . $this->name);
    }

    /**
     * Check the current cookie and return a user object if it's vaild.
     * @return boolean|\self 
     */
    public function checkCookie()
    {
        $cookie = !empty($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : FALSE;

        if (empty($cookie))
            return NULL;

        $parts = explode('-', $cookie);
        if (count($parts) != 2)
            return NULL;

        $id = $parts[0];

        $check = new self($id);

        if ($cookie != $check->makeCookie()) {
            return NULL;
        }

        return $check;
    }

    /**
     * Validate a user/password combination, returning userId.
     * 
     * @param type $name
     * @param type $pass
     * @return type 
     */
    public function validate($name, $pass)
    {
        $db = $this->getDb();
        $name = $db->escape($name);
        $pass = $db->escape($pass);
        $result = $db->run('SELECT id FROM ' . $this->_table . ' WHERE name=' . $name . ' AND password=' . $pass);

        $id = (!empty($result) && !empty($result['id'])) ? $result['id'] : FALSE;
        return $id;
    }

    public function __toString()
    {
        $this->_edit = Library_Login::$role > self::ROLE_EDITOR || Library_Login::$user->id == $this->id;
        return $this->editTag() . $this->name;
    }

}