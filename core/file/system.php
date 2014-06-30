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
        $files = array();
        $dirs = array();
        $recursive = ($options & self::RECURSIVE);
        $exclude_files = ($options & self::EXCLUDE_FILES);
        $exclude_dirs = ($options & self::EXCLUDE_DIRS);
        $inc_func = is_array($include) ? 'in_array' : 'strpos';
        $exc_func = is_array($exclude) ? 'in_array' : 'strpos';
        if (is_dir($path)) {
            if (substr($path, -1) == '/') {
                $path = substr($path, 0, -1);
            }
            $skip = array('.' => 1, '..' => 1);
            $objects = scandir($path);
            foreach ($objects as $entry) {
                if (isset($skip[$entry])
                    || (!empty($include) && $inc_func($entry, $include) === false)
                    || (!empty($exclude) && $exc_func($entry, $exclude) !== false)
                ) {
                    continue;
                }
                $curfile = $path . '/' . $entry;
                $file = $entry;
                if (is_dir($curfile)) {
                    $dirs[$entry] = (!$recursive) ? array() : self::listFiles($curfile, $options, $include, $exclude);
                } else {
                    $files[$entry] = $file;
                }
            }
        } else {
            throw new \Exception("Not listing a directory: {$path}");
        }
        #Combine dirs and files in proper order, maintaining keys.
        $result = array();
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
     * @throws \Exception
     */
    public static function rrmdir($dir, $self = true)
    {
        $result = true;
        if (is_dir($dir)) {
            $entries = scandir($dir);
            foreach ($entries as $entry) {
                if ($entry != '.' && $entry != '..') {
                    $file = $dir . '/' . $entry;
                    if (is_dir($file)) {
                        self::rrmdir($file);
                    } else {
                        $success = unlink($file);
                        if (!$success) {
                            throw new \Exception($file, 'Could not remove directory.');
                        }
                    }
                }
            }
            if ($self) {
                $result = rmdir($dir);
            }
        }
        if (!$result) {
            throw new \Exception($dir, 'Could not remove directory.');
        }
    }

    /**
     * Get the extension of a file.
     * @param type $file
     * @return type
     */
    public static function getExtension($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * Strip the extension of a file + path.
     * @param type $file
     * @return type
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
     * @return boolean success
     */
    public static function mkpath($path)
    {
        $result = true;
        if (!file_exists($path)) {
            $result = mkdir($path, 0777);
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
			</object></div>';
    }
}