<?php
namespace Core\Parse;
/**
 * Yaml parser, parses an yaml file to associative array.
 */
class Yaml
{

    /**
     * Simple parser for INI files with a very clean approach.
     *
     * This parser supports multiple inheritance from other sections.
     *
     * @param string $filename
     * @param int $depth How deep you want the recursive parsing to be.
     * @return array
     * @throws \Exception
     */
    public static function parse($filename)
    {
        $result = null;
        if (function_exists('yaml_parse_file')) {
            $result = yaml_parse_file($filename);
        } else if (file_exists(PATH_VENDOR . 'Yaml/Parser.php')) {
            //Vendor YAML parser, based on Symphony yaml parser.
            $yaml = new \Yaml\Parser();
            $result = $yaml->parse(file_get_contents($filename), false, false);
        }
        return $result;
    }
}
