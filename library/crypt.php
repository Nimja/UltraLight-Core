<?php

/**
 * Encode/Decode an array of numbers into a simple string that fits as a filename.
 * 
 */
class Library_Crypt
{

    /**
     * String of characters.
     * @var type 
     */
    private static $chars = "0Za9Yb8Xc7WdV6eUfT5gShR4iQj3PkO2lNm1MnLoKpJqIrHsGtFuEvDwCxByAz";

    /**
     * Encoding lookup arrayl.
     * @var array 
     */
    private static $encode_chars = NULL;

    /**
     * Decoding lookup array.
     * @var array 
     */
    private static $decode_chars = NULL;

    /**
     * Highest number we can encode in x digits.
     * 
     * @var array
     */
    private static $limit = NULL;

    /**
     * Create the lookup arrays.
     */
    private static function makeLookupArrays()
    {
        $chars = self::$chars;
        $len = strlen($chars);
        self::$encode_chars = str_split($chars);
        self::$decode_chars = array_flip(self::$encode_chars);

        #Highest number = 14776335;
        for ($i = 1; $i < 5; $i++) {
            $limit[$i] = pow($len, $i);
        }
        self::$limit = $limit;
    }

    /**
     * Encode array of numbers into a string, for a filename.
     * @param array $numbers 
     * @param array 
     * @return string
     */
    public static function encode($numbers)
    {
        if (empty($numbers) || !is_array($numbers)) {
            Show::error('Trying to encode with empty array.');
            return FALSE;
        }

        if (empty(self::$limit))
            self::makeLookupArrays();

        #Find how many digits we need to use.
        $digits = 1;
        foreach ($numbers as $number) {
            if ($number <= 0 || !is_numeric($number)) {
                Show::error('Data needs to be numbers.');
                return FALSE;
            }
            foreach (self::$limit as $digit => $limit) {
                if ($number < $limit) {
                    $digits = ($digits < $digit) ? $digit : $digits;
                    break;
                }
            }
        }

        #Encrypt the numbers.
        $result = array();
        foreach ($numbers as $number) {
            $number = intval($number);
            $cur = '';
            if ($digits > 1) {
                for ($i = $digits; $i > 1; $i--) {
                    $limit = self::$limit[$i - 1];
                    $curnum = floor($number / $limit);
                    $cur .= self::$encode_chars[$curnum];
                    $number -= $curnum * $limit;
                }
            }
            $cur .= self::$encode_chars[$number];
            $result[] = $cur;
        }
        return $digits . implode('', $result);
    }

    /**
     *
     * @param type $string 
     */
    public static function decode($string)
    {
        if (empty($string))
            ;

        #First character is a digit, denoting digits.
        $digits = substr($string, 0, 1);

        #Strip first char.
        $string = substr($string, 1);

        if (!is_numeric($digits) && $digits > 0) {
            Show::error('Improper string for decoding.');
            return FALSE;
        }

        $digits = intval($digits);

        if (strlen($string) % $digits != 0) {
            Show::error('String is incorrect length.');
            return FALSE;
        }

        if (empty(self::$limit))
            self::makeLookupArrays();

        $parts = str_split($string, $digits);
        $result = array();
        foreach ($parts as $part) {
            $cur = 0;
            if ($digits > 1) {
                $letters = str_split($part);
                $part = array_pop($letters);
                foreach ($letters as $key => $letter) {
                    $limit = self::$limit[$digits - $key - 1];
                    $cur += self::$decode_chars[$letter] * $limit;
                }
            }
            $cur += self::$decode_chars[$part];
            $result[] = $cur;
        }

        return $result;
    }

}