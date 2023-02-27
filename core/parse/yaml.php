<?php

namespace Core\Parse;

require_once PATH_CORE . '../composer/vendor/autoload.php';

/**
 * Yaml parser, parses an yaml file to associative array.
 */
class Yaml
{

    /**
     * Parse YAML string.
     * @param string $string
     * @return array
     */
    public static function parseString($string)
    {
        $result = null;
        //Symphony YAML parser.
        $result = \Symfony\Component\Yaml\Yaml::parse($string);
        return $result;
    }

    /**
     * Parse YAML file.
     * @param string $fileName
     * @return array
     */
    public static function parse($fileName)
    {
        if (file_exists($fileName)) {
            return self::parseString(file_get_contents($fileName));
        }
        return null;
    }
}
