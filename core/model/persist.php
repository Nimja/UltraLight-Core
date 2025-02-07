<?php

namespace Core\Model;

/**
 * Persistent model accross sessions. Sets a single cookie with some protection.
 *
 * This class is useful to store certain data (model Ids) in. A strong limitation is that this class will only
 * be used if cookies are enabled.
 *
 * @author Nimja
 */
class Persist extends Sessioned
{
    const COOKIE_NAME = 'persist';
    const COOKIE_DELIMITER = '-';
    const COOKIE_EXPIRE = '+6 months';
    /**
     * The code for this persitance session, can be MD5, imei or whatever.
     *
     * This, together with ID, created a cookie.
     *
     * @db-type varchar
     * @db-length 64
     * @var string
     */
    public $code;
    /**
     * Expire date unix timestamp.
     * @db-type bigint
     * @db-unsigned
     * @var int
     */
    public $expireDate;
    /**
     * Stored data.
     * @db-type mediumtext
     * @serialize
     * @var array
     */
    public $data = [];

    /**
     * Objects we're storing in the session.
     * @var array
     */
    public $sessionData = [];

    /**
     * Attempt to save in DB, but only if we have cookies enabled.
     * @return void
     */
    private function _attemptSave()
    {
        if (\Request::hasCookies()) {
            $this->save();
        } else {
            $this->saveSession();
        }
    }

    /**
     * Save the object, to both DB and session.
     * @return \Core\Model\Persist
     */
    public function save()
    {
        if (empty($this->code)) {
            $this->code = md5($this->id . microtime());
        }
        $this->expireDate = strtotime(self::COOKIE_EXPIRE);
        $result = parent::save();
        $this->_setCookie();
        return $result;
    }

    /**
     * Save session, setting the time.
     * @return \Core\Model\Persist
     */
    public function saveSession()
    {
        $this->sessionData['time'] = time();
        return parent::saveSession();
    }

    /**
     * Attempt to set cookie after saving.
     * @return void
     */
    private function _setCookie()
    {
        $value = $this->id . self::COOKIE_DELIMITER . $this->code;
        \Request::setCookie(self::COOKIE_NAME, $value, self::COOKIE_EXPIRE);
    }

    /**
     * Verify if correct cookie is set.
     * @return type
     */
    private function _hasCorrectCookie()
    {
        $cookie = \Request::getCookie(self::COOKIE_NAME);
        return $cookie == $this->id . self::COOKIE_DELIMITER . $this->code;
    }

    /**
     * Validate that the correct cookie is set.
     * @return \Core\Model\Persist
     */
    public function validateCookie()
    {
        if ($this->id && $this->code) {
            if (!$this->_hasCorrectCookie()) {
                $this->_setCookie();
            }
            $this->saveSession();
        } else if (!empty($this->data)) {
            $this->_attemptSave();
        }
        return $this;
    }

    /**
     * Set value. If value is different from current value, we will attempt to save this object.
     *
     * We only attempt save if the value is different or the object is not yet in ID.
     *
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }
        $curValue = $this->get($name);
        if ($curValue != $value || !$this->id) {
            if (!blank($value)) {
                $this->data[$name] = $value;
            } else if (isset($this->data[$name])) {
                unset($this->data[$name]);
            }
            $this->_attemptSave();
        } else {
            $this->saveSession();
        }
    }

    /**
     * Get variable.
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }
        return getKey($this->data, $name);
    }

    /**
     * Set model by class and object.
     * @param string $class
     * @param \Core\Model $entity
     * @param boolean $sessionOnly If set to true, we do not store it in the database.
     * @return \Core\Model\Persist
     */
    public function setModel($class, $entity, $sessionOnly = false)
    {
        if (empty($class) || !is_subclass_of($class, \Core\Model::class)) {
            return $this;
        }
        $class = \Sanitize::className($class);
        if ($entity instanceof \Core\Model) {
            $this->sessionData[$class] = $entity;
            if ($sessionOnly) {
                $this->saveSession();
            } else {
                $this->set($class, $entity->id);
            }
        } else {
            unset($this->sessionData[$class]);
            $this->set($class, null);
        }
        return $this;
    }

    /**
     * Retrieve model from session or DB, if we get from database, we add this information to the session.
     * @param string $class
     * @return \Core\Model|null
     */
    public function getModel($class)
    {
        if (empty($class) || !is_subclass_of($class, \Core\Model::class)) {
            return null;
        }
        $class = \Sanitize::className($class);
        $result = null;
        $entity = getKey($this->sessionData, $class, null);
        $savedId = $this->get($class);
        if ($entity instanceof \Core\Model && $entity->id == $savedId) {
            $result = $entity;
        } else if (!empty($savedId)) {
            $result = $class::load($savedId);
            if ($result) {
                $this->setModel($class, $result);
            }
        }
        return $result;
    }

    /**
     * Check if persistance works, by extension checks if cookies are enabled and if sessions are functional.
     * @return boolean
     */
    public static function isEnabled()
    {
        $result = false;
        $persist = self::loadSession();
        if ($persist && !empty($persist->sessionData['time'])) {
            $result = true;
        }
        return $result;
    }

    /**
     * Get the current persist, based on session or cookie.
     * @return \Core\Model\Persist
     */
    public static function getCurrent()
    {
        $result = self::loadSession();
        if (!$result && \Request::hasCookies()) {
            $result = self::_loadFromCookie();
        }
        if (!$result) {
            $result = new self();
        }
        return $result->validateCookie()->saveSession();
    }

    /**
     * Load persist class from cookie.
     * @return self|null
     */
    private static function _loadFromCookie()
    {
        $cookie = \Request::getCookie(self::COOKIE_NAME);
        # If the cookie is empty or does not contain the delimiter.
        if (empty($cookie) || strpos($cookie, self::COOKIE_DELIMITER) === false) {
            return null;
        }
        list($id, $code) = explode(self::COOKIE_DELIMITER, $cookie, 2);
        $persist = self::load(intval($id));
        $result = null;
        // If the persist exists AND the code matches, we load it.
        if ($persist && $persist->code == $code) {
            $result = $persist;
        }
        return $result;
    }
}
