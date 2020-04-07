<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's form.
 */
final class Form extends Component {

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->element_name = 'form';
        $this->attr('method', 'POST');
    }

}
