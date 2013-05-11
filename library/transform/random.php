<?php

/**
 * Class to take a randomly selected value from a string, split up by the | symbol.
 *
 * @author Nimja
 */
class Library_Transform_Random extends Library_Transform_Abstract {

    public function parse()
    {
        $parts = explode('|', $this->_value);
        $value = $parts[array_rand($parts)];
        return trim($value);
    }

}
