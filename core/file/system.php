<?php

namespace Core\File;

/**
 * Contains a bunch of helper functions that are semi-often used in places.
 */
class System
{
    const RECURSIVE = 1;
    const EXCLUDE_DIRS = 2;
    const EXCLUDE_FILES = 4;

    /**
     * list all files in a dir, optional recursivity.
     * @param string $path
     * @param int $options Options can be: self::RECURSIVE, self::EXCLUDE_DIRS, self::EXCLUDE_FILES
     * Combine options self::RECURSIVE | self::EXCLUDE_FILES (retrieves recursive directory tree)
     * @param $include Supply a string for "in string" or an array for specific names.
     * @param $exclude Same as include, but for excluding.
     * @return array Result is a named array with filenames as keys and values or dir contents as value.
     */
    public static function listFiles($path, $options = 0, $include = null, $exclude = null)
    {
        // Fail fast.
        if (!is_dir($path)) {
            throw new \Exception("Not listing a directory: {$path}");
        }
        $files = [];
        $dirs = [];
        // Parse options.
        $recursive = ($options & self::RECURSIVE);
        $exclude_files = ($options & self::EXCLUDE_FILES);
        $exclude_dirs = ($options & self::EXCLUDE_DIRS);
        $inc_func = is_array($include) ? 'in_array' : 'strpos';
        $exc_func = is_array($exclude) ? 'in_array' : 'strpos';
        // Remove trailing slash.
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            $path = substr($path, 0, -1);
        }
        $objects = scandir($path);
        foreach ($objects as $entry) {
            $curfile = $path . DIRECTORY_SEPARATOR . $entry;
            // Skip the recursive entries.
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // Include list enabled and this file is not in it.
            $isNotIncluded = (!empty($include) && $inc_func($entry, $include) === false);
            // Exclude list enabled and this file is not in it.
            $isNotExcluded = (!empty($exclude) && $exc_func($entry, $exclude) !== false);
            // Is not a directory when we're recursive.
            $isNotRecursiveDir = (!is_dir($curfile) || !$recursive);
            if ($isNotRecursiveDir && ($isNotIncluded || $isNotExcluded)) {
                continue;
            }
            // Add entry to results.
            $file = $entry;
            if (is_dir($curfile)) {
                $dirs[$entry] = (!$recursive) ? [] : self::listFiles($curfile, $options, $include, $exclude);
            } else {
                $files[$entry] = $file;
            }
        }
        // Combine dirs and files in proper order, maintaining keys.
        $result = [];
        if (!$exclude_dirs) {
            $result += $dirs;
        }
        if (!$exclude_files) {
            $result += $files;
        }
        return $result;
    }

    /**
     * Nice recursive remove.
     * @param string $dir
     * @param boolean $self Include self.
     * @param boolean $includeHidden If we should include hidden files (starting with dot).
     * @throws \Exception
     */
    public static function rrmdir($dir, $self = true, $includeHidden = false)
    {
        $result = true;
        if (is_dir($dir)) {
            $entries = scandir($dir);
            foreach ($entries as $entry) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                if (!$includeHidden && substr($entry, 0, 1) == '.') {
                    continue;
                }
                $file = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($file)) {
                    self::rrmdir($file);
                } else {
                    $success = unlink($file);
                    if (!$success) {
                        throw new \Exception("Could not remove file: {$file}");
                    }
                }
            }
            if ($self) {
                $result = rmdir($dir);
            }
        }
        if (!$result) {
            throw new \Exception("Could not remove directory: {$dir}");
        }
        return $result;
    }

    /**
     * Chmod that only works if the script runner is the same as the file owner.
     *
     * @param string $file
     * @param int $permissions
     */
    public static function chmod($file, $permissions = 0666)
    {
        if (posix_getuid() === fileowner($file)) {
            chmod($file, $permissions);
        }
    }

    /**
     * Make sure file exists and attempt to set correct permissions.
     *
     * @param string $file
     * @param int $permissions
     */
    public static function ensureFile($file, $permissions = 0666)
    {
        if (!file_exists($file)) {
            touch($file);
        }
        self::chmod($file, $permissions);
    }

    /**
     * Get the extension of a file.
     * @param string $file
     * @return string
     */
    public static function getExtension($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * Strip the extension of a file + path.
     * @param string $file
     * @return string
     */
    public static function stripExtension($file)
    {
        $parts = pathinfo($file);
        $len = 0;
        if (!empty($parts['extension'])) {
            $len = strlen($parts['extension']) + 1;
        }
        return substr($file, 0, -$len);
    }

    /**
     * Nice mkdir wrapper, not creating double and enforcing chmod.
     * @param string $path
     * @param int $permissions
     * @param bool $recursive
     * @return boolean success
     */
    public static function mkpath($path, $permissions = 0777, $recursive = false)
    {
        $result = true;
        if (!file_exists($path)) {
            $oldUmask = umask(0);
            $result = mkdir($path, $permissions, $recursive);
            umask($oldUmask);
        } else {
            self::chmod($path, $permissions);
        }
        return $result;
    }

    /**
     * Insert swf.
     * @param string $file
     * @param int $width
     * @param int $height
     * @param string $bgcolor
     * @param string $quality
     * @return string
     */
    public static function insertSwf($file, $width, $height, $bgcolor = '#FFFFFF', $quality = 'medium')
    {
        return '<div class="flashmovie" style="width: ' . $width . 'px; height: ' . $height . 'px;"><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="' . $file . '" width="' . $width . '" height="' . $height . '" codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
			<param name="movie" value="' . $file . '" />
			<param name="quality" value="' . $quality . '" />
			<param name="bgcolor" value="' . $bgcolor . '" />
			<param name="allowScriptAccess" value="sameDomain" />
			<param name="allowFullScreen" value="true" />
			<param name="menu" value="false" />

			<embed src="' . $file . '" quality="' . $quality . '" bgcolor="' . $bgcolor . '"
				width="' . $width . '" height="' . $height . '" name="' . $file . '" align="middle"
				quality="' . $quality . '"
				allowScriptAccess="sameDomain"
				allowFullScreen="true"
				menu="false"
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer" />
			</object></div><script src="//assets.nimja.com/js/ruffle/ruffle.js"></script>';
    }
}
