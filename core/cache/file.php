<?php namespace Core\Cache;
/**
 * Basic caching function, using the file system and cache folder.
 */
class File extends \Core\Cache
{
    const EXTENSION = '.cache';

    /**
     * Load from the cache.
     * @param string $key
     * @param int $expireTime
     * @return mixed
     */
    public function load($key, $expireTime = 0)
    {
        $fileName = $this->_makeFileName($key);
        $result = null;
        if (file_exists($fileName) && filemtime($fileName) >= $expireTime) {
            $result = unserialize(file_get_contents($fileName));
        }
        return $result;
    }

    /**
     * Save to the cache.
     * @param string $key
     * @param mixed $content
     * @return boolean
     */
    public function save($key, $content)
    {
        $fileName = $this->_makeFileName($key);
        $folder = dirname($fileName);
        if (!file_exists($folder)) {
            $oldUmask = umask(0);
            mkdir($folder, 0777, true);
            umask($oldUmask);
        }
        if (empty($content) && file_exists($fileName)) {
            unlink($fileName);
        } else {
            file_put_contents($fileName, serialize($content));
            chmod($fileName, 0666);
        }
        return true;
    }

    /**
     * Delete from cache.
     * @param string $key
     * @return boolean
     */
    public function delete($key)
    {
        $fileName = $this->_makeFileName($key);
        $result = false;
        if (file_exists($fileName)) {
            $result = unlink($fileName);
        }
        return $result;
    }

    /**
     * Clear all files for this group.
     * @return boolean
     */
    public function deleteAll()
    {
        \Core\File\System::rrmdir($this->_getPath(), false, true);
    }
    /**
     * List all for this group abstract.
     * @return boolean
     */
    public function listAll()
    {
        $files = \Core\File\System::listFiles($this->_getPath(), \Core\File\System::EXCLUDE_DIRS);
        $result = [];
        foreach (array_keys($files) as $file) {
            $result[] = pathinfo($file, PATHINFO_FILENAME);
        }
        return $result;
    }

    /**
     * Get path for this method.
     * @return string
     */
    protected function _getPath()
    {
        return PATH_CACHE . $this->_group . DIRECTORY_SEPARATOR;
    }

    /**
     * Make the filename.
     * @param string $key
     * @return string
     */
    protected function _makeFileName($key)
    {
        if (!is_string($key)) {
            throw new \Exception("Must give name for cache.");
        } else {
            $file = $this->_cleanKey($key) . self::EXTENSION;
        }
        return $this->_getPath() . $file;
    }

    /**
     * Create cache key.
     * @param array $args
     * @return string
     */
    public static function createKey($args)
    {
        $key = !empty($args) ? implode('_', $args) : 'call';
        return empty($key) ? 'empty' : $key;
    }
}
