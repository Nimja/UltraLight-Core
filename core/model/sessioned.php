<?php
namespace Core\Model;
/**
 * Sessioned class, meaning that on save the object is stored in the session for easy retrieval.
 *
 * @author Nimja
 */
abstract class Sessioned extends \Core\Model
{

    /**
     * The session variable.
     * @var \Core\Session
     */
    private static $_session;
    /**
     * Save the object, to both DB and session.
     * @return type
     */
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
     * @return \Core\Session
     */
    protected static function _getSession()
    {
        if (empty(self::$_session)) {
            self::$_session = new \Core\Session('Models');
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
        $result = $session->get($class);
        return ($result instanceof self) ? $result : null;
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