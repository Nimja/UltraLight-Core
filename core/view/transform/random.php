<?php
namespace Core\View\Transform;
/**
 * Class to take a randomly selected value from a string, split up by the | symbol.
 */
class Random extends Base {

    protected function _parse()
    {
        $parts = explode('|', $this->_value);
        $value = $parts[array_rand($parts)];
        return trim($value);
    }

}
