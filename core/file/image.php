<?php namespace Core\File;
/**
 * Image class, that wraps around loading an image and makes it easier.
 */
class Image
{
    const TYPE_PNG = 'png';
    const TYPE_JPEG = 'jpg';
    /**
     * Current type, for output.
     * @var string
     */
    public $type;
    /**
     * Current image object.
     * @var resource an image resource identifier on success
     */
    public $img;
    /**
     * Width of the image, in pixels.
     * @var int
     */
    public $width = 0;
    /**
     * Height of the image, in pixels.
     * @var int
     */
    public $height = 0;
    /**
     * Megapixels of this image.
     * @var float
     */
    public $mp = 0;

    /**
     * Create image, always need a filename.
     * @param string|int $fileName Or width if creating a blank image.
     * @param int
     * @param string $type
     * @throws Exception
     */
    public function __construct($fileName, $height = 0, $type = self::TYPE_PNG)
    {
        if (empty($height)) {
            $this->_load($fileName);
        } else if (is_numeric($fileName) && $height > 0) {
            $this->_create($fileName, $height);
            $this->setType($type);
        } else {
            throw new \Exception("Wrong parameters?");
        }
    }

    /**
     * Create new image.
     * @param mixed $width
     * @param mixed $height
     */
    private function _create($width, $height)
    {
        $this->width = intval($width);
        $this->height = intval($height);
        $this->img = imagecreatetruecolor($this->width, $this->height);
        $this->_calculateMp();
    }

    /**
     * Load image from file.
     * @return boolean
     */
    private function _load($fileName)
    {
        if (!file_exists($fileName)) {
            throw new \Exception("Image not found; $fileName");
        }
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        #Load image
        switch ($extension) {
            case 'png': $this->img = imagecreatefrompng($fileName);
                break;
            case 'jpg':
            case 'jpeg': $this->img = imagecreatefromjpeg($fileName);
                break;
            case 'gif': $this->img = imagecreatefromgif($fileName);
                break;
        }
        if ($this->img === false) {
            throw new \Exception("Unable to load image.");
        }
        $size = getimagesize($fileName);
        $this->width = $size[0];
        $this->height = $size[1];
        $this->_calculateMp();
    }

    /**
     * Calculate megapixels in the image, mostly for fun.
     * @return void
     */
    private function _calculateMp()
    {
        $this->mp = round($this->width * $this->height / 1000000, 1);
    }

    public function setType($type)
    {
        $types = [self::TYPE_PNG, self::TYPE_JPEG];
        if (!in_array($type, $types)) {
            throw new \Exception("Wrong type given: $type");
        }
        $this->type = $type;
    }

    /**
     * Return image string, for output to browser.
     */
    public function render()
    {
        ob_start();
        if ($this->type == self::TYPE_JPEG) {
            imagejpeg($this->img);
        } else {
            imagepng($this->img);
        }
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}
