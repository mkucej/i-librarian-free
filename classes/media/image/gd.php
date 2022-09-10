<?php

namespace Librarian\Media\Image;

use Exception;

class Gd implements ImageDriver {

    /**
     * Constructor.
     *
     * @throws Exception
     */
    public function __construct() {

        // Check GD.
        if (extension_loaded('gd') === false) {

            throw new Exception("PHP GD extension not installed", 500);
        }

    }

    /**
     * Create empty image.
     *
     * @param integer $width
     * @param integer $height
     * @return object|resource
     */
    public function create(int $width, int $height) {

        return imagecreatetruecolor($width, $height);
    }

    /**
     * Create image from a file.
     *
     * @param string $file
     * @return \GdImage|resource
     * @throws Exception
     */
    public function createFromFile(string $file) {

        switch (mime_content_type($file)) {

            case 'image/png':
                $image = imagecreatefrompng($file);
                break;

            case 'image/jpg':
            case 'image/jpeg':
            $image = imagecreatefromjpeg($file);
                break;

            default:
                throw new Exception("cannot open image of this type", 501);
        }

        if ($image === false) {

            throw new Exception("could not open the image", 500);
        }

        // Alpha.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    public function getWidth($image): int {

        return imagesx($image);
    }

    public function getHeight($image): int {

        return imagesy($image);
    }

    /**
     * Crop image.
     *
     * @param $image
     * @param integer $offsetX
     * @param integer $offsetY
     * @param integer $width
     * @param integer $height
     * @return false|\GdImage|resource
     * @throws Exception
     */
    public function crop($image, int $offsetX, int $offsetY, int $width, int $height) {

        if ($width < 0 || $height < 0 || $width > 10000 || $height > 10000) {

            throw new Exception("cannot crop image to required size", 422);
        }

        $rect = ["x" => $offsetX, "y" => $offsetY, "width" => $width, "height" => $height];

        $newImage = imagecrop($image, $rect);

        imagedestroy($image);

        return $newImage;
    }

    /**
     * Render image and return string or save to disk.
     *
     * @param $image
     * @param string $type
     * @param int|null $quality
     * @param string|null $filter
     * @param string|null $file
     * @return boolean|string
     * @throws Exception
     */
    public function render($image, string $type, int $quality = null, string $filter = null, string $file = null) {

        if (!isset($file)) {

            ob_start();
        }

        switch ($type) {

            case 'png':
                $png_quality = $quality ?? 4;
                $png_filter = $filter ?? PNG_NO_FILTER;
                imagepng($image, $file, $png_quality, $png_filter);
                break;

            case 'jpg':
            case 'jpeg':
                $jpg_quality = $quality ?? 85;
                imagejpeg($image, $file, $jpg_quality);
                break;

            default:
                throw new Exception("image type not implemented", 501);
        }

        if (!isset($file)) {

            return ob_get_clean();
        }

        return true;
    }

    /**
     * Destructor. Destroy image instance to free memory.
     */
    public function destroy($image) {

        imagedestroy($image);
    }
}
