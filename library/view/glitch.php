<?php
/**
 * Class to make text look glitched (randomly switches the character's case).
 *
 * @author Nimja
 */
class Library_View_Glitch
{

    public static function parse($string)
    {
        $letters = str_split($string);
        foreach ($letters as $key => $letter) {
            if (rand(0, 16) < 16) {
                continue;
            }
            $letters[$key] = ctype_upper($letter) ? strtolower($letter) : strtoupper($letter);
        }
        return implode('', $letters);
    }
}
