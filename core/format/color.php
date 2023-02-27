<?php

namespace Core\Format;

/**
 * Useful color class for fading and HSL/HSV calculations.
 * @property-read int $red Red value, from 0 to 255.
 * @property-read int $green Green value, from 0 to 255.
 * @property-read int $blue Blue value, from 0 to 255.
 * @property-read int $gray Gray value, from 0 to 1.
 * @property-read int $hue Hue value, from 0 to 360.
 * @property-read float $saturation Saturation value, from 0 to 1.
 * @property-read float $lightness Lightness value, from 0 to 1.
 * @property-read boolean $valid
 */
class Color
{
    const GAMMA = 1.5;
    /**
     * All named CSS colors according to: http://www.w3schools.com/cssref/css_colornames.asp
     * @var array
     */
    public static $cssColors = array(
        'aliceblue' => '#F0F8FF',
        'antiquewhite' => '#FAEBD7',
        'aqua' => '#00FFFF',
        'aquamarine' => '#7FFFD4',
        'azure' => '#F0FFFF',
        'beige' => '#F5F5DC',
        'bisque' => '#FFE4C4',
        'black' => '#000000',
        'blanchedalmond' => '#FFEBCD',
        'blue' => '#0000FF',
        'blueviolet' => '#8A2BE2',
        'brown' => '#A52A2A',
        'burlywood' => '#DEB887',
        'cadetblue' => '#5F9EA0',
        'chartreuse' => '#7FFF00',
        'chocolate' => '#D2691E',
        'coral' => '#FF7F50',
        'cornflowerblue' => '#6495ED',
        'cornsilk' => '#FFF8DC',
        'crimson' => '#DC143C',
        'cyan' => '#00FFFF',
        'darkblue' => '#00008B',
        'darkcyan' => '#008B8B',
        'darkgoldenrod' => '#B8860B',
        'darkgray' => '#A9A9A9',
        'darkgreen' => '#006400',
        'darkkhaki' => '#BDB76B',
        'darkmagenta' => '#8B008B',
        'darkolivegreen' => '#556B2F',
        'darkorange' => '#FF8C00',
        'darkorchid' => '#9932CC',
        'darkred' => '#8B0000',
        'darksalmon' => '#E9967A',
        'darkseagreen' => '#8FBC8F',
        'darkslateblue' => '#483D8B',
        'darkslategray' => '#2F4F4F',
        'darkturquoise' => '#00CED1',
        'darkviolet' => '#9400D3',
        'deeppink' => '#FF1493',
        'deepskyblue' => '#00BFFF',
        'dimgray' => '#696969',
        'dodgerblue' => '#1E90FF',
        'firebrick' => '#B22222',
        'floralwhite' => '#FFFAF0',
        'forestgreen' => '#228B22',
        'fuchsia' => '#FF00FF',
        'gainsboro' => '#DCDCDC',
        'ghostwhite' => '#F8F8FF',
        'gold' => '#FFD700',
        'goldenrod' => '#DAA520',
        'gray' => '#808080',
        'green' => '#008000',
        'greenyellow' => '#ADFF2F',
        'honeydew' => '#F0FFF0',
        'hotpink' => '#FF69B4',
        'indianred' => '#CD5C5C',
        'indigo' => '#4B0082',
        'ivory' => '#FFFFF0',
        'khaki' => '#F0E68C',
        'lavender' => '#E6E6FA',
        'lavenderblush' => '#FFF0F5',
        'lawngreen' => '#7CFC00',
        'lemonchiffon' => '#FFFACD',
        'lightblue' => '#ADD8E6',
        'lightcoral' => '#F08080',
        'lightcyan' => '#E0FFFF',
        'lightgoldenrodyellow' => '#FAFAD2',
        'lightgray' => '#D3D3D3',
        'lightgreen' => '#90EE90',
        'lightpink' => '#FFB6C1',
        'lightsalmon' => '#FFA07A',
        'lightseagreen' => '#20B2AA',
        'lightskyblue' => '#87CEFA',
        'lightslategray' => '#778899',
        'lightsteelblue' => '#B0C4DE',
        'lightyellow' => '#FFFFE0',
        'lime' => '#00FF00',
        'limegreen' => '#32CD32',
        'linen' => '#FAF0E6',
        'magenta' => '#FF00FF',
        'maroon' => '#800000',
        'mediumaquamarine' => '#66CDAA',
        'mediumblue' => '#0000CD',
        'mediumorchid' => '#BA55D3',
        'mediumpurple' => '#9370DB',
        'mediumseagreen' => '#3CB371',
        'mediumslateblue' => '#7B68EE',
        'mediumspringgreen' => '#00FA9A',
        'mediumturquoise' => '#48D1CC',
        'mediumvioletred' => '#C71585',
        'midnightblue' => '#191970',
        'mintcream' => '#F5FFFA',
        'mistyrose' => '#FFE4E1',
        'moccasin' => '#FFE4B5',
        'navajowhite' => '#FFDEAD',
        'navy' => '#000080',
        'oldlace' => '#FDF5E6',
        'olive' => '#808000',
        'olivedrab' => '#6B8E23',
        'orange' => '#FFA500',
        'orangered' => '#FF4500',
        'orchid' => '#DA70D6',
        'palegoldenrod' => '#EEE8AA',
        'palegreen' => '#98FB98',
        'paleturquoise' => '#AFEEEE',
        'palevioletred' => '#DB7093',
        'papayawhip' => '#FFEFD5',
        'peachpuff' => '#FFDAB9',
        'peru' => '#CD853F',
        'pink' => '#FFC0CB',
        'plum' => '#DDA0DD',
        'powderblue' => '#B0E0E6',
        'purple' => '#800080',
        'red' => '#FF0000',
        'rosybrown' => '#BC8F8F',
        'royalblue' => '#4169E1',
        'saddlebrown' => '#8B4513',
        'salmon' => '#FA8072',
        'sandybrown' => '#F4A460',
        'seagreen' => '#2E8B57',
        'seashell' => '#FFF5EE',
        'sienna' => '#A0522D',
        'silver' => '#C0C0C0',
        'skyblue' => '#87CEEB',
        'slateblue' => '#6A5ACD',
        'slategray' => '#708090',
        'snow' => '#FFFAFA',
        'springgreen' => '#00FF7F',
        'steelblue' => '#4682B4',
        'tan' => '#D2B48C',
        'teal' => '#008080',
        'thistle' => '#D8BFD8',
        'tomato' => '#FF6347',
        'turquoise' => '#40E0D0',
        'violet' => '#EE82EE',
        'wheat' => '#F5DEB3',
        'white' => '#FFFFFF',
        'whitesmoke' => '#F5F5F5',
        'yellow' => '#FFFF00',
        'yellowgreen' => '#9ACD32',
    );
    /**
     * Gamma value, defaults to 1.5
     * @var int
     */
    private $_gamma = self::GAMMA;
    /**
     * Red value, from 0 to 255.
     * @var int
     */
    private $_red = 0;
    /**
     * Red value, from 0 to 255.
     * @var int
     */
    private $_green = 0;
    /**
     * Red value, from 0 to 255.
     * @var int
     */
    private $_blue = 0;
    /**
     * Gray value, from 0 to 1.
     * @var float
     */
    private $_gray = 0;
    /**
     * Hue value, from 0 to 360
     */
    private $_hue = 0;
    /**
     * Saturation value, from 0 to 1.
     */
    private $_saturation = 0;
    /**
     * Lightness value, from 0 to 1.
     */
    private $_lightness = 0;
    /**
     * If the last set color was valid.
     * Only relevant vor setCss.
     */
    private $_valid = false;

    /**
     * Create with css color or RGB in an array.
     * @param string|array $color
     * @param float $gamma
     */
    public function __construct($color = null, $gamma = self::GAMMA)
    {
        if (is_array($color)) {
            list($red, $green, $blue) = $color;
            $this->setRgb($red, $green, $blue);
            $this->_valid = true;
        } else if ($color) {
            $this->setCss($color);
        }
        $this->_gamma = floatval($gamma);
    }

    /**
     * Allows named color or #123456 type color.
     * @param string $color
     * @return self
     */
    public function setCss($color)
    {
        $lowerCase = strtolower($color);
        $useColor = isset(self::$cssColors[$lowerCase]) ? self::$cssColors[$lowerCase] : $lowerCase;
        $clean = str_replace('#', '', $useColor);
        if (preg_match('/^[a-f0-9]{3,6}$/i', $clean)) {
            $this->_setHex($clean);
            $this->_valid = true;
        } else {
            $this->_valid = false;
        }
        return $this;
    }

    /**
     * Set RGB from hex.
     * @param string $hex
     */
    private function _setHex($hex)
    {
        if (strlen($hex) == 3) {
            $this->_red = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $this->_green = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $this->_blue = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $this->_red = hexdec(substr($hex, 0, 2));
            $this->_green = hexdec(substr($hex, 2, 2));
            $this->_blue = hexdec(substr($hex, 4, 2));
        }
        $this->_calculateHsv();
    }

    /**
     * Set RGB
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return Color
     */
    public function setRgb($red, $green, $blue)
    {
        $this->_red = round($red);
        $this->_green = round($green);
        $this->_blue = round($blue);
        $this->_calculateHsv();
        return $this;
    }

    /**
     * Set HSV.
     * @param int $hue
     * @param float $saturation
     * @param float $lightness
     * @return Color
     */
    public function setHsv($hue, $saturation, $lightness)
    {
        $this->_hue = $hue;
        $this->_saturation = $saturation;
        $this->_lightness = $lightness;
        $this->_calculateRgb();
        return $this;
    }

    /**
     * Invert this color, returning a new object.
     * @return Color
     */
    public function invert()
    {
        $result = $this->copy();
        $result->setRgb(
            255 - $this->_red,
            255 - $this->_green,
            255 - $this->_blue
        );
        return $result;
    }

    /**
     * Adjust HSV, returning a new object.
     * @param int $hue
     * @param int $saturation
     * @param int $lightness
     * @return self
     */
    public function adjustHsv($hue, $saturation = 0, $lightness = 0)
    {
        $result = $this->copy();
        $result->setHsv(
            $this->_hue + $hue,
            $this->_saturation + $saturation,
            $this->_lightness + $lightness
        );
        return $result;
    }

    /**
     * Fade from this color to another, returning a new object.
     * @param Color $color
     * @param float $amount From 0 to 1.
     * @return self
     */
    public function fadeTo($color, $amount)
    {
        $fadeAmount = $this->_limit($amount, 0, 1);
        if ($amount == 0) {
            return $this->copy();
        } else if ($amount == 1) {
            return $color->copy();
        }
        $result = $this->copy();
        $result->setRgb(
            $this->_fadeValue($this->_red, $color->red, $fadeAmount),
            $this->_fadeValue($this->_green, $color->green, $fadeAmount),
            $this->_fadeValue($this->_blue, $color->blue, $fadeAmount)
        );
        return $result;
    }

    /**
     * Get gray value, from 0 to 1 from averages.
     *
     * For balanced gray, just use $this->gray.
     *
     * @return int
     */
    public function getLiteralGray()
    {
        return ($this->_red + $this->_green + $this->_blue) / 765;
    }

    /**
     * Set brightness from 0 to 1, where 0 is black and 1 is white.
     *
     * Since we're already fading, you can also adjust how strong you want this effect to be.
     *
     * @param float $brightness
     * @param float $fadeAmount
     * @return self
     */
    public function setBrightness($brightness, $fadeAmount = 1)
    {
        $result = $this->copy();
        $brightnessTarget = $this->_limit($brightness, 0, 1);
        $fadeAmountLimited = $this->_limit($fadeAmount, 0, 1);
        if ($fadeAmountLimited == 0) {
            return $result;
        }
        // From 0 .. 1
        $gray = $this->getLiteralGray();
        $diff = ($brightnessTarget - $gray);
        if ($diff == 0) { // We're on target.
            return $result;
        } else if ($diff > 0) { // Destination is brighter.
            $fadeTo = 255;
            $range = 1 - $gray; // How much white is left.
            $fadeAmount = $diff * (1 / $range); // Scale the fading by the range.
            // $fadeAmount = $diff;
        } else { // Destination is darker.
            $fadeTo = 0;
            $range = $gray; // How much dark is left.
            $fadeAmount = -$diff * (1 / $range);
        }
        $fadeAmount *= $fadeAmountLimited;
        $result->setRgb(
            $this->_fadeValue($this->_red, $fadeTo, $fadeAmount),
            $this->_fadeValue($this->_green, $fadeTo, $fadeAmount),
            $this->_fadeValue($this->_blue, $fadeTo, $fadeAmount)
        );
        return $result;
    }

    /**
     * Get CSS color.
     * @return string
     */
    public function __toString()
    {
        $result = "#";
        $result .= str_pad(dechex($this->_red), 2, "0", STR_PAD_LEFT);
        $result .= str_pad(dechex($this->_green), 2, "0", STR_PAD_LEFT);
        $result .= str_pad(dechex($this->_blue), 2, "0", STR_PAD_LEFT);
        return $result;
    }

    /**
     * Get RGBA css string.
     */
    public function getRgba($alpha = 1)
    {
        $values = [
            $this->_red,
            $this->_green,
            $this->_blue,
            $alpha
        ];
        return 'rgba(' . implode(', ', $values) . ')';
    }

    /**
     * Get colors as array, with multiplier.
     *
     * This is useful to get them from 0..1 for example.
     * @param array $alpha
     * @param float $multiplier
     * @return array
     */
    public function getAsArray($alpha = 1, $multiplier = 1)
    {
        return [
            $this->_red * $multiplier,
            $this->_green * $multiplier,
            $this->_blue * $multiplier,
            $alpha,
        ];
    }

    /**
     * Get a copy from this object.
     * @return self
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Fade from one color to the next.
     *
     * This uses squared values, as it provides a MUCH better visual result.
     * @param float $from
     * @param float $to
     * @param float $amount
     * @return float
     */
    private function _fadeValue($from, $to, $amount)
    {
        $fromG = pow($from, $this->_gamma);
        $toG = pow($to, $this->_gamma);
        $diff = $toG - $fromG;
        $result = $fromG + $diff * $amount;
        return pow($result, 1 / $this->_gamma);
    }

    /**
     * Limit value.
     * @param float $value
     * @param float $min
     * @param float $max
     * @return float
     */
    private function _limit($value, $min, $max)
    {
        $lowerLimit = $value < $min ? $min : $value;
        return $lowerLimit > $max ? $max : $lowerLimit;
    }

    /**
     * Limit values.
     */
    private function _limitColors()
    {
        $this->_red = $this->_limit(round($this->_red), 0, 255);
        $this->_green = $this->_limit(round($this->_green), 0, 255);
        $this->_blue = $this->_limit(round($this->_blue), 0, 255);
        $this->_hue = round($this->_hue) % 360;
        if ($this->_hue < 0) {
            $this->_hue += 360;
        }
        $this->_saturation = $this->_limit($this->_saturation, 0, 1);
        $this->_lightness = $this->_limit($this->_lightness, 0, 1);
    }

    /**
     * Calculate Hue, Saturation and Lightness/Value.
     */
    private function _calculateHsv()
    {
        $this->_limitColors();
        $r = $this->_red / 255;
        $g = $this->_green / 255;
        $b = $this->_blue / 255;
        $this->_calculateGray();

        //Find min and max.
        $min = min($r, $g, $b);
        $max = max($r, $g, $b);

        $this->_hue = 0;
        $this->_saturation = 0;
        $this->_lightness = $max;

        $maxDelta = $max - $min;

        if ($maxDelta != 0) {
            $this->_saturation = $maxDelta / $max;

            $hr = ((($max - $r) / 6) + ($maxDelta / 2)) / $maxDelta;
            $hg = ((($max - $g) / 6) + ($maxDelta / 2)) / $maxDelta;
            $hb = ((($max - $b) / 6) + ($maxDelta / 2)) / $maxDelta;

            if ($r == $max) {
                $h = $hb - $hg;
            } else if ($g == $max) {
                $h = (1 / 3) + $hr - $hb;
            } else {
                $h = (2 / 3) + $hg - $hr;
            }
            if ($h < 0) {
                $h++;
            }
            if ($h > 1) {
                $h--;
            }
            $this->_hue = $h * 360;
        }
        $this->_limitColors();
    }

    /**
     * Calculate RGB.
     */
    private function _calculateRgb()
    {
        $this->_limitColors();
        // Hue part, from 0 to 6.
        $huePart = floor($this->_hue / 60);
        // Fraction, from 0..1.
        $hueFraction = ($this->_hue % 60) / 60;
        $grayValue = $this->_lightness * (1 - $this->_saturation);
        $nValue = $this->_lightness * (1 - $this->_saturation * $hueFraction);
        $kValue = $this->_lightness * (1 - $this->_saturation * (1 - $hueFraction));
        switch ($huePart) {
            case 0:
                $red = $this->_lightness;
                $green = $kValue;
                $blue = $grayValue;
                break;
            case 1:
                $red = $nValue;
                $green = $this->_lightness;
                $blue = $grayValue;
                break;
            case 2:
                $red = $grayValue;
                $green = $this->_lightness;
                $blue = $kValue;
                break;
            case 3:
                $red = $grayValue;
                $green = $nValue;
                $blue = $this->_lightness;
                break;
            case 4:
                $red = $kValue;
                $green = $grayValue;
                $blue = $this->_lightness;
                break;
            case 5:
            case 6:
                $red = $this->_lightness;
                $green = $grayValue;
                $blue = $nValue;
                break;
        }
        $this->_red = $red * 255;
        $this->_green = $green * 255;
        $this->_blue = $blue * 255;
        $this->_calculateGray();
        $this->_limitColors();
    }

    /**
     * Calculate the gray value of the current RGB.
     * @return void
     */
    private function _calculateGray()
    {
        $this->_gray = (0.3 * $this->_red + 0.6 * $this->_green + 0.1 * $this->_blue) / 255;
    }

    /**
     * Public access to red, green, blue, hue, saturation, lightness
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $attribute = "_{$name}";
        return isset($this->$attribute) ? $this->$attribute : 0;
    }
}
