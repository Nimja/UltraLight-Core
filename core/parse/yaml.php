<?php namespace Core\Parse;
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
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($string);
        } else if (file_exists(PATH_VENDOR . 'Yaml/Parser.php')) {
            //Vendor YAML parser, based on Symphony yaml parser.
            $yaml = new \Yaml\Parser();
            $result = $yaml->parse($string, false, false);
        }
        return $result;
    }

    /**
     * Parse YAML file.
     * @param string $fileName
     * @return array
     */
    public static function parse($fileName)
    {
        $result = null;
        if (function_exists('yaml_parse_file')) {
            $result = yaml_parse_file($fileName);
        } else if (file_exists(PATH_VENDOR . 'Yaml/Parser.php')) {
            //Vendor YAML parser, based on Symphony yaml parser.
            $yaml = new \Yaml\Parser();
            $result = $yaml->parse(file_get_contents($fileName), false, false);
        }
        return $result;
    }
}