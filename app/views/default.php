<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\TextView;

/**
 * Class DefaultView.
 *
 * Send data to client as is. Used for simple JSON responses.
 */
class DefaultView extends TextView {

    /**
     * @param null $input
     * @return string
     * @throws Exception
     */
    public function main($input = null) {

        $this->write($input);

        return $this->send();
    }

    /**
     * @param string $line
     * @throws Exception
     */
    public function sseLine(string $line) {

        $this->append($line);
        $this->sendChunk(strlen($line));
    }
}
