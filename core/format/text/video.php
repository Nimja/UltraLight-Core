<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Video extends Base
{
    const SOURCE_FORMATS = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    /**
     * Minimum parameter count, file, width, height.
     * @var int
     */
    protected $_minParameterCount = 3;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $file = $this->_reverseParse($parts[0]);
        $width = $this->_reverseParse($parts[1]);
        $height = $this->_reverseParse($parts[2]);
        $class = isset($parts[3]) ? $this->_reverseParse($parts[3]) : false;
        $extra = $class ? " class= \"{$class}\"" : '';

        $sources = [];
        foreach (self::SOURCE_FORMATS as $ext => $mimeType) {
            $fileName = $file . '.' . $ext;
            if (file_exists(PATH_ASSETS . $fileName)) {
                $sources[] = "<source src=\"/assets/{$fileName}\" type=\"{$mimeType}\">";
            }
        }
        $sourceStrings = implode(PHP_EOL, $sources);
        return "<video width=\"{$width}\" height=\"{$height}\" controls loop{$extra}>{$sourceStrings}</video>";
    }
}
