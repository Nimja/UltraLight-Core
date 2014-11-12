<?php
namespace Core\View\Transform;
/**
 * Class to take a randomly selected value from a string, split up by the | symbol.
 */
class Random extends \Core\View\Transform {

    public function parse()
    {
        $parts = explode('|', $this->_value);
        $value = $parts[array_rand($parts)];
        return trim($value);
    }

}
