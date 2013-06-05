<?php

/**
 * The script class.
 */
class Library_Session {

    /**
     * Setting a variable with this value means it gets deleted.
     */
    const DELETE = 'DELETE';
    /**
     * The key for the session.
     * @var type
     */
    private $_key = 'session';

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
    public function set($name, $value)
    {
        if ($value == self::DELETE) {
            unset($this->_variables[$name]);
        } else {
            $this->_variables[$name] = $value;
        }
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
            foreach ($this->_variables as $key => $value) {
                if (isset($lookup[$key])) {
                    continue;
                }
                unset($this->_variables[$key]);
            }
        }
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
    public function flush()
    {
        $_SESSION[$this->_key] = $this->_variables;
    }

}
