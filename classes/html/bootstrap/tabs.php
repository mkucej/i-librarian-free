<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's navigation tabs.
 */
final class Tabs extends Nav {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('nav-tabs');
    }
}
