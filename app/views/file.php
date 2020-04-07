<?php

namespace LibrarianApp;

use Exception;

/**
 * Class FileView.
 *
 * Send a file to client in chunks.
 */
class FileView extends \Librarian\Mvc\FileView {

    /**
     * Main.
     *
     * @param string $disposition
     * @return string
     * @throws Exception
     */
    public function main(string $disposition = 'inline'): string {

        return $this->send($disposition);
    }
}
