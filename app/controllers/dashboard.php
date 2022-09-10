<?php

namespace LibrarianApp;

use Exception;

class DashboardController extends AppController {

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        $this->session->close();

        $this->authorization->signedId(true);
        $this->authorization->permissions('G');

        $model = new DashboardModel($this->di);
        $data = $model->get();
        $model = null;

        $view = new DashboardView($this->di);
        return $view->main($data);
    }
}
