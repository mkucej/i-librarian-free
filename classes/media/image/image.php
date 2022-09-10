<?php

namespace Librarian\Media\Image;

use Exception;

final class Image {

    private static ImageDriver $driver;
    public int $height;
    public int $width;

    /**
     * @var \GdImage|\Imagick|resource
     */
    private $image;

    /**
     * @param string $driver
     * @return Image
     * @throws Exception
     */
    public static function driver(string $driver): Image {

        if ($driver === 'gd') {

            self::$driver = new Gd();

        } elseif ($driver === 'imagick') {

            self::$driver = new Imagick();

        } else {

            throw new Exception('image driver not found');
        }

        return new Image();
    }

    /**
     * Create image from a file.
     *
     * @param string $file
     * @return void
     * @throws Exception
     */
    public function createFromFile(string $file): void {

        $this->image  = self::$driver->createFromFile($file);
        $this->width  = self::$driver->getWidth($this->image);
        $this->height = self::$driver->getHeight($this->image);
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

        $this->image = self::$driver->crop($this->image, $offsetX, $offsetY, $width, $height);
        $this->width = self::$driver->getWidth($this->image);
        $this->height = self::$driver->getHeight($this->image);
    }

    /**
     * Render image and return string or save to disk.
     *
     * @param string $type
     * @param int|null $quality
     * @param string|null $filter
     * @param string|null $file
     * @return boolean|string
     */
    public function render(string $type, int $quality = null, string $filter = null, string $file = null) {

        return self::$driver->render($this->image, $type, $quality, $filter, $file);
    }

    /**
     * Save image to disk.
     *
     * @param string $file
     * @param int|null $quality
     * @param string|null $filter
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

        self::$driver->destroy($this->image);
    }
}
