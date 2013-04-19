<?php
/**
 * Class to take a randomly selected value from a string, split up by the | symbol.
 *
 * @author Nimja
 */
class Library_View_Random
{

    public static function parse($string)
    {
        $parts = explode('|', $string);
        $value = $parts[array_rand($parts)];
        return trim($value);
    }
}
