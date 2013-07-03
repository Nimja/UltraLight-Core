<?php

/**
 * Class to make text look glitched (randomly switches the character's case).
 *
 * @author Nimja
 */
class Library_Transform_Glitch extends Library_Transform_Abstract {

    public function parse()
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