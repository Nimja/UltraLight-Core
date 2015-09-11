<?php namespace Core\Format\Text;
/**
 * Parser for blocks.
 */
abstract class Base
{
    const DIVIDER = '|';
    /**
     * Reverse map for translation.
     * @var array
     */
    protected static $_reverseMap = [
        '<b>' => '^',
        '</b>' => '^',
        '<i>' => '_',
        '</i>' => '_',
        '<s>' => '--',
        '</s>' => '--',
    ];
    /**
     * Parameter count.
     * @var int
     */
    protected $_minParameterCount = 2;

    /**
     * Parse string into the required data.
     * @param string $data
     * @return string
     */
    public function parse($data)
    {
        $parts = explode(self::DIVIDER, $data);
        if (count($parts) < $this->_minParameterCount) {
            $result = 'Wrong amount of parameters: ' . $data;
        } else {
            $result = $this->_parse($parts);
        }
        return $result;
    }

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    abstract protected function _parse($parts);

    /**
     * Reverse (parts) of text to remove the parsed underscores, etc.
     * @param string $string
     * @return string
     */
    protected function _reverseParse($string)
    {
        return str_replace(array_keys(self::$_reverseMap), self::$_reverseMap, $string);
    }
}
