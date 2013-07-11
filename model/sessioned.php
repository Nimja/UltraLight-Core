<?php
/**
 * Sessioned class, meaning that on save the object is stored in the session for easy retrieval.
 *
 * @author Nimja
 */
abstract class Model_Sessioned extends Model_Abstract
{

    /**
     * The session variable.
     * @var Library_Session
     */
    private static $_session;

    public function save()
    {
        parent::save();
        return $this->saveSession();
    }

    /**
     * Save the current entity in the session.
     * @return /self
     */
    public function saveSession()
    {
        $class = $this->_class;
        $session = $class::_getSession();
        $session->set($class, $this);
        return $this;
    }

    /**
     * Get current session.
     * @return Library_Session
     */
    protected static function _getSession()
    {
        if (empty(self::$_session)) {
            self::$_session = new Library_Session('Models');
        }
        return self::$_session;
    }
    /**
     * Load the current entity in the session.
     * @return /self
     */
    public static function loadSession()
    {
        $class = get_called_class();
        $session = self::_getSession();
        return $session->get($class);
    }
    /**
     * Clear the entity from the session.
     */
    public static function clearSession()
    {
        $class = get_called_class();
        $session = self::_getSession();
        $session->set($class);
    }
}