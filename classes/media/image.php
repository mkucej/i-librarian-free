<?php

namespace Librarian\Media;

use Exception;

final class Image {

    public $height;
    private $image;
    public $width;

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
     * @return void
     */
    public function create(int $width, int $height): void {

        $this->image = imagecreatetruecolor($width, $height);

        $this->saveAlpha(true);

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Create image from a file.
     *
     * @param string $file
     * @return void
     * @throws Exception
     */
    public function createFromFile(string $file): void {

        switch (mime_content_type($file)) {

            case 'image/png':
                $this->image = imagecreatefrompng($file);
                break;

            case 'image/jpg':
            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($file);
                break;

            default:
                throw new Exception("cannot open image of this type", 501);
        }

        if ($this->image === false) {

            throw new Exception("could not open the image", 500);
        }

        $this->saveAlpha(true);

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Create image from string.
     *
     * @param string $string
     * @return void
     * @throws Exception
     */
    public function createFromString(string $string): void {

        $this->image = imagecreatefromstring($string);

        if ($this->image === false) {

            throw new Exception("could not open the image", 500);
        }

        $this->saveAlpha(true);

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Flag whether to save alpha channel.
     *
     * @param boolean $saveAlpha
     */
    public function saveAlpha(bool $saveAlpha = true): void {

        imagealphablending($this->image, !$saveAlpha);
        imagesavealpha($this->image, $saveAlpha);
    }

    /**
     * Crop image.
     *
     * @param  integer $offsetX
     * @param  integer $offsetY
     * @param  integer $width
     * @param  integer $height
     * @throws Exception
     */
    public function crop(int $offsetX, int $offsetY, int $width, int $height): void {

        if ($width < 0 || $height < 0 || $width > 10000 || $height > 10000) {

            throw new Exception("cannot crop image to required size", 422);
        }

        $rect = ["x" => $offsetX, "y" => $offsetY, "width" => $width, "height" => $height];

        $newImage = imagecrop($this->image, $rect);

        imagedestroy($this->image);

        $this->image = $newImage;

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Resize image.
     *
     * @param  integer|null $width
     * @param  integer|null $height
     * @throws Exception
     */
    public function resize(int $width = null, int $height = null): void {

        if ($width === null && $height === null) {

            throw new Exception("cannot resize image, width or height must be specified", 400);
        }

        if (($width < 1 && $height < 1) || $width > 10000 || $height > 10000) {

            throw new Exception("cannot resize image to required size", 422);
        }

        if ($width === null) {

            $width = ceil($this->width * ($height / $this->height));

        } elseif ($height === null) {

            $height = ceil($this->height * ($width / $this->width));
        }

        $new_image = imagescale($this->image, $width, $height, IMG_BILINEAR_FIXED);

        imagedestroy($this->image);
        $this->image = $new_image;

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Render image and return string or save to disk.
     *
     * @param string $type
     * @param integer $quality
     * @param string $filter
     * @param string $file
     * @return boolean|string
     * @throws Exception
     */
    public function render(string $type, int $quality = null, string $filter = null, string $file = null) {

        if (!isset($file)) {

            ob_start();
        }

        switch ($type) {

            case 'png':
                $pngQuality = isset($quality) ? $quality : 4;
                $pngFilter = isset($filter) ? $filter : PNG_NO_FILTER;
                imagepng($this->image, $file, $pngQuality, $pngFilter);
                break;

            case 'jpg':
            case 'jpeg':
                $jpgQuality = isset($quality) ? $quality : 85;
                imagejpeg($this->image, $file, $jpgQuality);
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
     * Save image to disk.
     *
     * @param  string  $file
     * @param  integer $quality
     * @param  string  $filter
     * @throws Exception
     */
    public function save(string $file, int $quality = null, string $filter = null): void {

        setlocale(LC_ALL,'en_US.UTF-8');
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        switch ($extension) {

            case 'png':
                $type = 'png';
                break;

            case 'jpg':
            case 'jpeg':
                $type = 'jpg';
                break;

            default:
                throw new Exception("image type not implemented", 501);
        }

        $this->render($type, $quality, $filter, $file);
    }

    /**
     * Destructor. Destroy image instance to free memory.
     */
    public function __destruct() {

        if (is_resource($this->image) === true) {

            imagedestroy($this->image);
        }

    }

    /**
     * Add a custom IPTC tag.
     *
     * @param string $filename
     * @param string $value
     * @throws Exception
     */
    public function writeIPTCTag(string $filename, string $value): void {

        $image = getimagesize($filename);

        if (isset($image['mime']) === false || $image['mime'] !== 'image/jpeg') {

            throw new Exception(__METHOD__ . ' requires a JPEG image');
        }

        $length = strlen($value);

        $retval = chr(0x1C) . chr(2) . chr(230);
        $retval .= chr($length >> 8) . chr($length & 0xFF);

        // Embed.
        $content = iptcembed($retval . $value, $filename);

        // Write.
        $fp = fopen($filename, "wb");
        fwrite($fp, $content);
        fclose($fp);
    }

    /**
     * Read IPTC tag.
     *
     * @param string $filename
     * @return string
     */
    public function readIPTCTag(string $filename): string {

        $info = [];

        getimagesize($filename, $info);
        $iptc = iptcparse($info['APP13']);

        return $iptc['2#230'][0] ?? null;
    }

}
