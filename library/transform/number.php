<?php

/**
 * Class to 'speak' out integers in a fairly large range.
 *
 * @author Nimja
 */
class Library_Transform_Number extends Library_Transform_Abstract {

    private $_hyphen = '-';
    private $_space = ' ';
    private $_negative = 'minus ';
    private $_dictionary = array(
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
        20 => 'twenty',
        30 => 'thirty',
        40 => 'fourty',
        50 => 'fifty',
        60 => 'sixty',
        70 => 'seventy',
        80 => 'eighty',
        90 => 'ninety',
        100 => 'hundred',
        1000 => 'thousand',
        1000000 => 'million',
        1000000000 => 'billion',
    );

    public function parse()
    {
        return $this->_translate(intval($this->_value));
    }

    private function _translate($number)
    {
        if ($number < 0) {
            $number = -$number;
            $result = $this->_negative . $this->_translate($number);
        } else if ($number < 21) {
            $result = $this->_dictionary[$number];
        } else if ($number < 100) {
            $tens = $number - $number % 10;
            $number -= $tens;
            $result = $this->_dictionary[$tens] . $this->_hyphen . $this->_translate($number);
        } else if ($number < 1000) {
            $result = $this->_highNumbers($number, 100);
        } else if ($number < 1000000) {
            $result = $this->_highNumbers($number, 1000);
        } else if ($number < 1000000000) {
            $result = $this->_highNumbers($number, 1000000);
        } else if ($number < 1000000000000) {
            $result = $this->_highNumbers($number, 1000000000);
        }
        return $result;
    }

    /**
     * A repeated function for very high numbers.
     * @param int $number
     * @param int $divider
     * @return string
     */
    private function _highNumbers($number, $divider)
    {
        $parts = floor($number / $divider);
        $number -= $parts * $divider;
        $result = $this->_translate($parts) .
                $this->_space .
                $this->_dictionary[$divider] .
                $this->_space .
                $this->_translate($number);
        return $result;
    }

}
