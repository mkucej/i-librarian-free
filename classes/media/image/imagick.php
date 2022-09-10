<?php

namespace Librarian\Media\Image;

use Exception;
use ImagickException;

class Imagick implements ImageDriver {

    /**
     * Constructor.
     *
     * @throws Exception
     */
    public function __construct() {

        // Check GD.
        if (extension_loaded('imagick') === false) {

            throw new Exception("PHP Imagick extension not installed", 500);
        }
    }

    /**
     * Create image from a file.
     *
     * @param string $file
     * @return \Imagick
     * @throws Exception
     */
    public function createFromFile(string $file) {

        $image = new \Imagick($file);
        $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_COPY);

        return $image;
    }

    /**
     * @param \Imagick $image
     * @return int
     * @throws ImagickException
     */
    public function getWidth($image): int {

        return $image->getImageWidth();
    }

    /**
     * @param \Imagick $image
     * @return int
     * @throws ImagickException
     */
    public function getHeight($image): int {

        return $image->getImageHeight();
    }

    /**
     * Crop image.
     *
     * @param \Imagick $image
     * @param integer $offsetX
     * @param integer $offsetY
     * @param integer $width
     * @param integer $height
     * @return \Imagick
     * @throws ImagickException
     * @throws Exception
     */
    public function crop($image, int $offsetX, int $offsetY, int $width, int $height) {

        if ($width < 0 || $height < 0 || $width > 10000 || $height > 10000) {

            throw new Exception("cannot crop image to required size", 422);
        }

        $image->cropImage($width, $height, $offsetX, $offsetY);

        return $image;
    }

    /**
     * Render image and return string or save to disk.
     *
     * @param \Imagick $image
     * @param string $type
     * @param int|null $quality
     * @param string|null $filter
     * @param string|null $file
     * @return \Imagick
     * @throws ImagickException
     * @throws Exception
     */
    public function render($image, string $type, int $quality = null, string $filter = null, string $file = null) {

        switch ($type) {

            case 'png':
                $png_quality = $quality ?? 4;
                $png_filter = $filter ?? PNG_NO_FILTER;
                $image->setOption('png:compression-level', $png_quality);
                $image->setOption('png:filter', $png_filter);
                $image->setImageFormat("png");
                break;

            case 'jpg':
            case 'jpeg':
                $jpg_quality = $quality ?? 85;
                $image->setImageCompressionQuality($jpg_quality);
                $image->setImageFormat("jpeg");
                break;

            default:
                throw new Exception("image type not implemented", 501);
        }

        if (isset($file)) {

            $image->writeImage($file);
        }

        return $image;
    }

    /**
     * Destructor. Destroy image instance to free memory.
     *
     * @param \Imagick $image
     */
    public function destroy($image) {

        $image->destroy();
    }
}
