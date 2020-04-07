<?php

namespace Librarian\Media;

use Librarian\Container\DependencyInjector;

final class Language {

    private $di;

    public function __construct(DependencyInjector $di) {

        $this->di = $di;
    }
}
