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

        // Translate and format info lines.
        if (isset($input['info'])) {
            $input['info'] = $this->lang->t9n($input['info']);
            $input['info'] = mb_strtoupper(mb_substr($input['info'], 0, 1)) . mb_substr($input['info'], 1)  . '.';
        }

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
