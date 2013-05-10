<?php
/**
 * Class to take a randomly selected value from a string, split up by the | symbol.
 *
 * @author Nimja
 */
class Library_Transform_Random extends Library_Transform_Abstract
{

    public function parse($command, $string)
    {
        $parts = explode('|', $string);
        $value = $parts[array_rand($parts)];
        return trim($value);
    }
}
