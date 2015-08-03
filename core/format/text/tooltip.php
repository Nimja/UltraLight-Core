<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Tooltip extends Base
{
    /**
     * Minimum parameter count.
     * @var type
     */
    protected $_minParameterCount = 2;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $text = $parts[0];
        $detail = htmlentities($parts[1]);
        return "<span class=\"hasTooltip\" data-toggle=\"tooltip\" title=\"{$detail}\">{$text}</span>";
    }
}
