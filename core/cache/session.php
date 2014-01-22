<?php
namespace Core\Cache;
/**
 * Basic caching function, using the file system and cache folder.
 */
class Session extends \Core\Cache
{
    /**
     * The currenct variables.
     * @var array
     */
    private $_variables;

    /**
     * Constructor; when creating a session; you need to use a group.
     * @param string $group
     */
    public function __construct($group)
    {
        parent::__construct($group);
        if (!isset($_SESSION)) {
            session_start();
        }
        $this->_variables = getKey($_SESSION, $this->_group);
        if (!is_array($this->_variables)) {
            $this->_variables = array();
        }
    }

    /**
     * Load from the cache.
     * @param string $cacheKey
     * @param int $expireTime
     * @return mixed
     */
    public function load($key, $time = 0)
    {
        $getValue = true;
        $key = $this->_cleanKey($key);
        if ($time > 0) {
            $curTime = getKey($this->_variables, $this->_timeKey($key), 0);
            if ($time > $curTime) {
                $getValue = false;
            }
        }
        return $getValue ? getKey($this->_variables, $key) : null;
    }

    /**
     * Save to the cache.
     * @param string $cacheKey
     * @param mixed $content
     * @return boolean
     */
    public function save($key, $content)
    {
        $key = $this->_cleanKey($key);
        $timeKey = $this->_timeKey($key);
        if (empty($content)) {
            unset($this->_variables[$key], $this->_variables[$timeKey]);
        } else {
            $this->_variables[$key] = $content;
            $this->_variables[$timeKey] = time();
        }
        $_SESSION[$this->_group] = &$this->_variables;
        return true;
    }

    /**
     * To store time, setting a timekey.
     * @param string $key
     * @return string
     */
    private function _timeKey($key)
    {
        return ".time.{$key}";
    }
}