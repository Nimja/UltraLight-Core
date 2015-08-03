<?php namespace Core\Format\Text;
/**
 * Parser for blocks.
 */
abstract class Base
{
    const DIVIDER = '|';
    /**
     * Parameter count.
     * @var type
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
}
