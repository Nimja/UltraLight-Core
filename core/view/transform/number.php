<?php
namespace Core\View\Transform;
/**
 * Class to 'speak' out integers in a fairly large range.
 *
 * @author Nimja
 */
class Number extends Base {

    private $_hasOutput = false;
    private $_hyphen = '-';
    private $_space = ' ';
    private $_negative = 'minus ';
    private $_dictionary = [
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
    ];

    protected function _parse()
    {
        return $this->_translate(intval($this->_value));
    }

    /**
     * Translate one block of a number into a value.
     *
     * We set output to true, because we only say 'zero' if it's the only number.
     *
     * This method works recursive for high numbers and negative numbers.
     *
     * @param int $number
     * @return string
     */
    private function _translate($number)
    {
        if ($number < 0) {
            $result = $this->_negative . $this->_translate(-$number);
        } else if ($number == 0) {
            $result = $this->_hasOutput ? '' : $this->_dictionary[0];
        } else if ($number < 21) {
            $result = $this->_dictionary[$number];
        } else if ($number < 100) {
            $tens = $number - $number % 10;
            $number -= $tens;
            $result = $this->_dictionary[$tens];
            if ($number > 0) {
                $result .= $this->_hyphen . $this->_translate($number);
            }
        } else if ($number < 1000) {
            $result = $this->_highNumbers($number, 100);
        } else if ($number < 1000000) {
            $result = $this->_highNumbers($number, 1000);
        } else if ($number < 1000000000) {
            $result = $this->_highNumbers($number, 1000000);
        } else if ($number < 1000000000000) {
            $result = $this->_highNumbers($number, 1000000000);
        } else {
            $result = 'A lot';
        }
        $this->_hasOutput = true;
        return $result;
    }

    /**
     * Recursive partial method for high numbers (everything above 99).
     *
     * From 100 and above, we add the multiplier (ie. hundred, thousand or million) after the block.
     *
     * @param int $number
     * @param int $divider
     * @return string
     */
    private function _highNumbers($number, $divider)
    {
        $integer = floor($number / $divider);
        $number -= $integer * $divider;
        $result = [$this->_translate($integer), $this->_dictionary[$divider]];
        if ($number != 0) {
            $result[] = $this->_translate($number);
        }
        return implode($this->_space, $result);
    }

}
