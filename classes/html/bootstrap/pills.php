<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's navigation pills.
 */
final class Pills extends Nav {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('nav-pills');
    }
}
