<?php
namespace Core;
/**
 * Wrapper for $_SESSION with a nice interface.
 * Also allows more transparently for multiple "sessions" next to each other without interfering.
 */
class Session {

    /**
     * Setting a variable with this value means it gets deleted.
     */
    const DELETE = 'DELETE';
    /**
     * The key for the session.
     * @var string
     */
    private $_key;

    /**
     * The array in memory.
     * @var array
     */
    private $_variables;

    /**
     * Basic constructor.
     */
    public function __construct($key = 'session')
    {
        $this->_key = $key;
        $this->_variables = getKey($_SESSION, $this->_key, array());
    }

    /**
     * Get variables from the memory.
     * @param string $name
     * @param mixed $default
     */
    public function get($name, $default = null)
    {
        return getKey($this->_variables, $name, $default);
    }

    /**
     * Set variables into the memory.
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value = null)
    {
        if ($value == self::DELETE || blank($value)) {
            unset($this->_variables[$name]);
        } else {
            $this->_variables[$name] = $value;
        }
        $this->_flush();
    }

    /**
     * Check if the session has a variable.
     * @param string $name
     * @return boolean
     */
    public function has($name)
    {
        return isset($this->_variables[$name]);
    }

    /**
     * Set variables from array.
     * @param array $array
     * @param string $prefix
     */
    public function setValues($values, $prefix = '')
    {
        if (is_array($values)) {
            foreach ($values as $name => $value) {
                $this->set($prefix . $name, $value);
            }
        }
    }

    /**
     * Empty the memory.
     */
    public function clear($exclude = array())
    {
        if (empty($exclude)) {
            $this->_variables = array();
        } else {
            $lookup = array_flip($exclude);
            $keys = array_keys($this->_variables);
            foreach ($keys as $key) {
                if (isset($lookup[$key])) {
                    continue;
                }
                unset($this->_variables[$key]);
            }
        }
        $this->_flush();
    }

    /**
     * Remove specific vars from the memory by regex.
     * @param string $regex
     * @return /self
     */
    public function remove($regex)
    {
        foreach (array_keys($this->_variables) as $key) {
            if (!preg_match($regex, $key)) {
                continue;
            }
            unset($this->_variables[$key]);
        }
        $this->_flush();
        return $this;
    }

    /**
     * Return the content as an array.
     * @return type
     */
    public function toArray()
    {
        return $this->_variables;
    }

    /**
     * Flush it to the session.
     */
    private function _flush()
    {
        $_SESSION[$this->_key] = $this->_variables;
    }

}
