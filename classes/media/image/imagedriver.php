<?php

namespace Librarian\Media\Image;

interface ImageDriver {

    public function createFromFile(string $file);

    public function getWidth($image): int;

    public function getHeight($image): int;

    public function crop($image, int $offsetX, int $offsetY, int $width, int $height);

    public function render($image, string $type, int $quality = null, string $filter = null, string $file = null);

    public function destroy($image);
}
