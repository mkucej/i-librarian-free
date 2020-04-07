<?php

namespace LibrarianApp;

use Exception;

/**
 * Class DetailsModel
 *
 * @method array read()
 */
class DetailsModel extends AppModel {

    /**
     * Read data.
     *
     * @return array
     * @throws Exception
     */
    protected function _read(): array {

        $this->db_main->run('SELECT sqlite_version()');
        $version = $this->db_main->getResult();

        return ['sqlite' => $version];
    }
}
