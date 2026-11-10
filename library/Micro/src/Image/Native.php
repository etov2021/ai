<?php

namespace Micro\Image;

class Native
{
    /**
     * Path to the file opened
     * @var string
     */
    protected $_imagePath;

    /**
     * Current image width
     *
     * @var integer
     */
    protected $_width;

    /**
     * Current image height
     *
     * @var integer
     */
    protected $_height;

    /**
     * The GD image resource
     *
     * @var resource
     */
    protected $_image;

    public function __construct($imagePath)
    {
        $this->open($imagePath);
    }

    /**
     * @param string $imagePath the $imagePath to set
     * @return Native
     * @throws \Exception
     */
    protected function _setImagePath($imagePath)
    {
        $imagePath = realpath($imagePath);

        if (!file_exists($imagePath) || is_dir($imagePath)) {
            throw new \Exception("{$imagePath} does not exist or is a directory");
        }

        $this->_imagePath = $imagePath;

        return $this;
    }

	/**
     * @return string The image file path
     */
    public function getImagePath()
    {
        return $this->_imagePath;
    }

	/**
     * @return integer
     */
    public function getHeight()
    {
        return $this->isLoaded() ? imagesy($this->_image) : 0;
    }

	/**
     * @return integer
     */
    public function getWidth()
    {
        return $this->isLoaded() ? imagesx($this->_image) : 0;
    }


    public function open($imagePath)
    {
        if ($this->isLoaded()) {
            $this->close();
        }

        $this->_load($imagePath);
    }

    public function close()
    {
        $this->_imagePath = null;

        $this->_destroyImage();
    }

    /**
     * Is image loaded
     *
     * @return boolean
     */
    public function isLoaded()
    {
        return is_resource($this->_image);
    }

    protected function _load($imagePath)
    {
        $this->_setImagePath($imagePath);

        $extension = $this->_getFileExtension($this->getImagePath());

        switch ($extension) {
            case 'jpeg':
            case 'jpg':
            	$this->_image = @imagecreatefromjpeg($imagePath);
                break;
            case 'gif':
                $this->_image = @imagecreatefromgif($imagePath);
                break;
            case 'png':
                $this->_image = @imagecreatefrompng($imagePath);
                break;
            default:
            	$this->_image = @imagecreatefromjpeg($imagePath);
                break;
        }

        if (!$this->isLoaded()) {
            throw new \Exception('Invalid image format');
        }
    }

    /**
     * Resize image
     *
     * @param integer $width
     * @param integer $height
     * @return boolean
     */
    public function resize($width, $height)
    {
    	 if (!$this->isLoaded()) {
    	 	return null;
    	 }

        $width  = abs($width);
        $height = abs($height);

        $tmpImage = @imagecreatetruecolor($width, $height);

        if (!$tmpImage) {
            return false;
        }

        $extension = $this->_getFileExtension($this->getImagePath());

        if (in_array($extension, array('gif', 'png'))) {

            imagealphablending($tmpImage, false);

            imagesavealpha($tmpImage,true);

            $transparent = imagecolorallocatealpha($tmpImage, 255, 255, 255, 127);

            imagefilledrectangle($tmpImage, 0, 0, $width, $height, $transparent);
        }

        $result = imagecopyresampled($tmpImage, // destination
                                     $this->_image, // source
                                     0, 0, // destination start point x,y
                                     0, 0, // source start point x,y
                                     $width, $height, // destination width, height
                                     $this->getWidth(), $this->getHeight()); // source width, height

        if (!$result) {
            $this->imageDestroy($tmpImage);
            return false;
        }

        $this->_destroyImage();

        $this->_image = $tmpImage;

        return true;
    }

    /**
     * Resize image keeping aspect ratio
     *
     * @param integer $width
     * @param integer $height
     * @return boolean
     */
    public function resizeKeepingAspect($width, $height)
    {
        $width  = abs($width);
        $height = abs($height);

        $currentWidth  = $this->getWidth();
        $currentHeight = $this->getHeight();

        if ($width > $currentWidth) {
            $width = $currentWidth;
        }

        if ($height > $currentHeight) {
            $height = $currentHeight;
        }

        $ratio = $currentWidth / $currentHeight;

        $newWidth = $width;
        $newHeight = $height;

        if (($newWidth / $newHeight) != $ratio) {

            $calcWidth  = round($newHeight * $ratio);
            $calcHeight = round($newWidth / $ratio);

            if ($calcHeight > $height) {
                $newWidth = $calcWidth;
            } else {
                $newHeight = $calcHeight;
            }
        }

        return $this->resize($newWidth, $newHeight);
    }

    /**
     * Resize image keeping aspect ratio
     * and fill other parts with specific color
     *
     * @param $width
     * @param $height
     * @param $background
     * @return boolean
     */
    public function resizeAndFill($width, $height, $background = 'ffffff')
    {
        if (!$this->isLoaded()) {
            return null;
        }

        if (is_string($background)) {
            $backgroundColor = $this->allocateColorHex($background);
        }

        $resizeResult = $this->resizeKeepingAspect($width, $height);

        if (!$resizeResult) {
            return false;
        }

        $tmpImage = imagecreatetruecolor($width, $height);

        if (is_string($background)) {
            $backgroundColor = $this->allocateColorHex($background);
            imagefill($tmpImage, 0, 0, $backgroundColor);
        }

        if ($this->getWidth() != $width) {
            $startX = round(($width - $this->getWidth()) / 2);
            $startY = 0;
        } else {
            $startX = 0;
            $startY = round(($height - $this->getHeight()) / 2);
        }

        $resizeResult = imagecopyresampled($tmpImage,
                                           $this->_image,
                                           $startX, $startY,
                                           0, 0,
                                           $this->getWidth(), $this->getHeight(),
                                           $this->getWidth(), $this->getHeight());

        if (!$resizeResult) {
            $this->imageDestroy($tmpImage);
            return false;
        }

        $this->_destroyImage();
        $this->_image = $tmpImage;

        return true;
    }

    /**
     * Save image to file
     *
     * @param string $imagePath
     * @param integer $quality
     * @return bool
     * @throws \Exception
     */
    public function save($imagePath = null, $quality = 75)
    {
        if (!$this->isLoaded()) {
            return null;
        }

        if (empty($imagePath)) {
            $imagePath = $this->getImagePath();
        }

        $extension = $this->_getFileExtension($imagePath);

        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $result = imagejpeg($this->_image, $imagePath, $quality);
                break;
            case 'gif':
                $result = imagegif($this->_image, $imagePath);
                break;
            case 'png':
                if ($quality > 10) {
                    $quality = 10 - round($quality / 10);
                }
                $result = imagepng($this->_image, $imagePath, $quality);
                break;
            default:
                throw new \Exception('Cannot determine file type from path: ' . $imagePath);
                break;
        }

        if ($result) {
            $this->_setImagePath($imagePath);
        }

        return $result;
    }

    /**
     * Allocate color from hexadecimal RGB string: (#)FEFEFE
     *
     * @param string $color
     * @param int $alpha 0-127
     * @return int
     * @throws \Exception
     */
    public function allocateColorHex($color = '000000', $alpha = 0)
    {
        $color = str_replace('#', '', $color);

        if (strlen($color) !== 6) {
            throw new \Exception("{$color} is not valid hex RGB color");
        }

        $intColor = hexdec("0x00{$color}");
        $r = $intColor >> 16 & 0xFF;
        $g = $intColor >> 8  & 0xFF;
        $b = $intColor & 0xFF;

        return imagecolorallocatealpha($this->_image, $r, $g, $b, $alpha);
    }

    /**
     * Allocate color from RGBA
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @return int
     */
    public function allocateColor($red, $green, $blue, $alpha = 0)
    {
        return imagecolorallocatealpha($this->_image, $red, $green, $blue, $alpha);
    }

    /**
     * Destroy image resource
     *
     * @param resource $image
     * @return null
     */
    public function imageDestroy($image)
    {
        @imagedestroy($image);
    }

    /**
     * Destroy image resource
     * @return void
     */
    protected function _destroyImage()
    {
        if (is_resource($this->_image)) {
            imagedestroy($this->_image);
        }
    }

    /**
     * Get the file extension
     * @param string $filePath
     * @return string
     */
    protected function _getFileExtension($filePath)
    {
        return strtolower(substr($filePath, strrpos($filePath, '.')+1));
    }

    /**
     * Cleanup
     *
     * @return null
     */
    public function __destruct()
    {
        $this->_destroyImage();
    }
}