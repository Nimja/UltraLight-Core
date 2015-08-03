<?php
namespace Core\View\Transform;
/**
 * Class to make text look glitched (randomly switches the character's case).
 */
class Glitch extends Base {

    protected function _parse()
    {
        $letters = str_split($this->_value);
        foreach ($letters as $key => $letter) {
            if (rand(0, 16) < 16) {
                continue;
            }
            $letters[$key] = ctype_upper($letter) ? strtolower($letter) : strtoupper($letter);
        }
        return implode('', $letters);
    }

}
