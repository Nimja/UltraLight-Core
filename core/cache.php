<?php
namespace Core;
/**
 * Abstract class for cache.
 */
abstract class Cache
{
    /**
     *
     * @var array
     */
    protected static $_instances = [];
    /**
     *
     * @var string
     */
    protected $_group;

    /**
     * Create cache instance for group.
     * @param string $group
     */
    public function __construct($group)
    {
        if (empty($group) || !is_string($group)) {
            throw new \Exception("Cache group must be a string.");
        }
        $this->_group = $this->_cleanKey($group);
    }

    /**
     * Load abstract.
     *
     * @param string $key
     * @param int $time
     * @return mixed
     */
    abstract public function load($key, $time = 0);

    /**
     * Save abstract.
     *
     * @param string $key
     * @param mixed $value
     * @return boolean
     */
    abstract public function save($key, $value);

    /**
     * Delete abstract.
     *
     * @param string $key
     * @return boolean
     */
    abstract public function delete($key);

    /**
     * Delete all for this group abstract.
     *
     * @return boolean
     */
    abstract public function deleteAll();

    /**
     * List all for this group abstract.
     *
     * @return array
     */
    abstract public function listAll();

    /**
     * Clean a string for cache key usage.
     * @param string $key
     * @return string
     */
    protected function _cleanKey($key) {
        $replace = [
            # For linux.
            '/', # Slashes, obviously.
            # For windows.
            '<', '>', ':', '"', '\'', '\\', '|', '?', '*',
        ];
        return trim(str_replace($replace, '+', \Core::cleanPath($key)), '+');
    }

    /**
     * Get instance for this group.
     * @param string $group
     * @return \Core\Cache
     */
    public static function getInstance($group)
    {
        $class = get_called_class();
        $name = $class . $group;
        if (!isset(self::$_instances[$name])) {
            self::$_instances[$name] = new $class($group);
        }
        return self::$_instances[$name];
    }
}