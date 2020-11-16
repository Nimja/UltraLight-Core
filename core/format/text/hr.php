<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Hr extends Base
{
    /**
     * Minimum parameter count.
     * @var int
     */
    protected $_minParameterCount = 1;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $class = isset($parts[0]) ? $this->_reverseParse($parts[0]) : '';
        $extra = $class && (strlen($class) > 1)? "class= \"{$class}\"" : '';
        return "<hr {$extra} \>";
    }
}
