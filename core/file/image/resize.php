<?php namespace Core\File\Image;
//Loading an image file.
class Resize
{
    const MODE_RATIO_CROPPING = 0;
    const MODE_RATIO_FITTING = 1;
    const MODE_FORCE_WIDTH = 2;
    const MODE_FORCE_HEIGHT = 3;
    const MODE_RATIO_FITTING_NO_BORDERS = 4;
    /**
     * The current mode for resizing.
     * @var int
     */
    private $_mode = self::MODE_RATIO_CROPPING;

    /**
     * Create the resizer with a mode.
     *
     * * MODE_RATIO_CROPPING = crop (cut edges)
     * * MODE_RATIO_FITTING = fit inside the border (fill edges);
     * * MODE_FORCE_WIDTH = fit horizontally (fill edges);
     * * MODE_FORCE_HEIGHT = fit vertically (fill edges);
     * * MODE_RATIO_FITTING_NO_BORDERS = fit (result might be smaller in w/h
     * @param int $mode
     */
    public function __construct($mode = self::MODE_RATIO_CROPPING)
    {
        $this->_mode = $mode;
    }

    /**
     * Make sure source is always an image, but allow filenames.
     * @param mixed $source
     * @return \Core\File\Image
     */
    private function _getImage($source)
    {
        return (!$source instanceof \Core\File\Image) ? new \Core\File\Image($source) : $source;
    }

    /**
     * Creates a new image as a thumbnail for the other.
     * @param \Core\File\Image|string $source
     * @param int $width
     * @param int $height
     * @param string $type File type.
     * @return \Core\File\Image
     */
    public function thumbnail($source, $width, $height, $type = \Core\File\Image::TYPE_PNG)
    {
        return $this->getResized($source, $width, $height, $type);
    }

    /**
     * Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
     * Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
     * Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality*setting.
     * Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments*must remain.
     *
     * Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.*
     * Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
     * 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
     * 2 = Up to 95 times faster.	Images appear a little sharp, some prefer this over a quality of 3.
     * 3 = Up to 60 times faster.	Will give high quality smooth results very close to imagecopyresampled, just faster.
     * 4 = Up to 25 times faster.	Almost identical to imagecopyresampled for most images.
     * 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.
     *
     * @param resource $dst_image
     * @param resource $src_image
     * @param int $dst_x x-coordinate of destination point.
     * @param int $dst_y y-coordinate of destination point.
     * @param int $src_x x-coordinate of source point.
     * @param int $src_y y-coordinate of source point.
     * @param int $dst_w Destination width.
     * @param int $dst_h Destination height.
     * @param int $src_w Source width.
     * @param int $src_h Source height.
     * @param type $quality
     * @return boolean true on success, false on failure
     */
    public static function imageCopyResampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h,
        $src_w, $src_h, $quality = 3)
    {
        if (empty($src_image) || empty($dst_image) || $quality <= 0) {
            return false;
        }
        if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
            $temp = imagecreatetruecolor($dst_w * $quality + 1, $dst_h * $quality + 1);
            imagecopyresized($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1,
                $src_w, $src_h);
            imagecopyresampled($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality,
                $dst_h * $quality);
            imagedestroy($temp);
        } else {
            imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        }
        return true;
    }

    /**
     * Copy/resize an image into another.
     * @param \Core\File\Image|string $source
     * @param \Core\File\Image|string $destination
     * @param int $width
     * @param int $height
     * @param int $x Optional x position of the destination image.
     * @param int $y Optional y position of the destination image.
     * @return type
     */
    public function resize($source, $destination, $width, $height, $x = 0, $y = 0)
    {
        $sourceImage = $this->_getImage($source);
        $destImage = $this->_getImage($destination);

        $sourceSize = new ResizeData(0, 0, $sourceImage->width, $sourceImage->height);
        $desiredSize = new ResizeData($x, $y, $width, $height);

        $scale = $this->_calculateScale($sourceSize, $desiredSize);
        $destSize = $this->_calculateSizes($sourceSize, $desiredSize, $scale);

        self::imageCopyResampled(
            $destImage->img, $sourceImage->img, $destSize->x + $desiredSize->x, $destSize->y + $desiredSize->y,
            $sourceSize->x, $sourceSize->y, $destSize->w, $destSize->h, $sourceSize->w, $sourceSize->h, 2
        );
        return $destImage;
    }

    /**
     * Get resized image, like thumbnail but a different resized result is possible, keeping aspect ratio.
     *
     * @param \Core\File\Image|string $source
     * @param int $width
     * @param int $height
     * @return \Core\File\Image
     */
    public function getResized($source, $width, $height, $type = \Core\File\Image::TYPE_JPEG)
    {
        $sourceImage = $this->_getImage($source);

        $sourceSize = new ResizeData(0, 0, $sourceImage->width, $sourceImage->height);
        $desiredSize = new ResizeData(0, 0, $width, $height);

        $scale = $this->_calculateScale($sourceSize, $desiredSize);
        $destSize = $this->_calculateSizes($sourceSize, $desiredSize, $scale);
        $destImage = new \Core\File\Image($destSize->w, $destSize->h, $type);

        self::imageCopyResampled(
            $destImage->img, $sourceImage->img, $destSize->x + $desiredSize->x, $destSize->y + $desiredSize->y,
            $sourceSize->x, $sourceSize->y, $destSize->w, $destSize->h, $sourceSize->w, $sourceSize->h, 2
        );
        return $destImage;
    }

    /**
     * Calculate scale of the image we're resizing.
     * @param ResizeData $sourceSize
     * @param ResizeData $desiredSize
     * @return float
     */
    private function _calculateScale($sourceSize, $desiredSize)
    {
        #Cropping or scaling.
        $scaleX = $desiredSize->w / $sourceSize->w;
        $scaleY = $desiredSize->h / $sourceSize->h;
        $scale = 1;
        switch ($this->_mode) {
            case self::MODE_FORCE_HEIGHT: //Force height
                $scale = $scaleY;
                break;
            case self::MODE_FORCE_WIDTH: //Force width
                $scale = $scaleX;
                break;
            case self::MODE_RATIO_FITTING_NO_BORDERS: //Fit without additional borders.
            case self::MODE_RATIO_FITTING: //Fit
                $scale = ($scaleX < $scaleY) ? $scaleX : $scaleY;
                break;
            default: //Ratio cropping
                $scale = ($scaleX > $scaleY) ? $scaleX : $scaleY;
                break;
        }
        return $scale;
    }

    /**
     * This function modifies the sizes to the correct result.
     * @param ResizeData $sourceSize
     * @param ResizeData $desiredSize
     * @param float $scale
     * @return ResizeData
     */
    private function _calculateSizes($sourceSize, $desiredSize, $scale)
    {
        // Destination size.
        $destSize = new ResizeData($sourceSize->x, $sourceSize->y, $sourceSize->w, $sourceSize->h);

        $destSize->w = floor($sourceSize->w * $scale);
        $destSize->h = floor($sourceSize->h * $scale);

        if ($this->_mode == self::MODE_RATIO_FITTING_NO_BORDERS) {
            $desiredSize->w = $destSize->w;
            $desiredSize->h = $destSize->h;
        } else {
            //Horizontal cropping
            if ($destSize->w > $desiredSize->w) {
                $newW = $desiredSize->w / $scale;
                $sourceSize->x = ($sourceSize->w - $newW) / 2;
                $sourceSize->w = $newW;
                $destSize->w = $desiredSize->w;
                //Horizontal fitting
            } elseif ($destSize->w < $desiredSize->w) {
                $destSize->x = ($desiredSize->w - $destSize->w) / 2;
            }

            //Vertical cropping
            if ($destSize->h > $desiredSize->h) {
                $newH = $desiredSize->h / $scale;
                $sourceSize->y = ($sourceSize->h - $newH) / 2;
                $sourceSize->h = $newH;
                $destSize->h = $desiredSize->h;
                //Vertical fitting
            } elseif ($destSize->h < $desiredSize->h) {
                $destSize->y = ($desiredSize->h - $destSize->h) / 2;
            }
        }
        return $destSize;
    }
}

/**
 * Very small class just for holding data.
 */
class ResizeData
{
    /**
     * X coordinate.
     * @var int
     */
    public $x;
    /**
     * Y coordinate.
     * @var int
     */
    public $y;
    /**
     * Width.
     * @var int
     */
    public $w;
    /**
     * Height.
     * @var int
     */
    public $h;

    /**
     * Basic constructor.
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     */
    public function __construct($x, $y, $w, $h)
    {
        $this->x = intval($x);
        $this->y = intval($y);
        $this->w = intval($w);
        $this->h = intval($h);
    }
}
