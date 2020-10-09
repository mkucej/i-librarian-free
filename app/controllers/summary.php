<?php

namespace LibrarianApp;

use Exception;
use Librarian\Mvc\Controller;

/**
 * Class SummaryController
 *
 * Item summary.
 */
class SummaryController extends Controller {

    /**
     * Main. Show item summary, or export it.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);

        // Id.
        if (isset($this->get['id']) === false) {

            throw new Exception("id parameter is required", 400);
        }

        $this->validation->id($this->get['id']);

        // Display type export.
        $display_type = isset($this->get['export']) ? 'export' : '';

        // Model.
        $model = new SummaryModel($this->di);
        $item = $model->item($this->get['id'], $display_type);

        // Render view.
        if ($display_type === 'export') {

            $style = '';

            if ($this->get['export'] === 'citation' && !empty($this->get['style'])) {

                $citation = new CitationModel($this->di);
                $style = $citation->getFromName($this->get['style']);
            }

            $view = new ItemsView($this->di);
            return $view->export(['items' => [$item]], $this->get['export'], $this->get['disposition'], $style);

        } else {

            $view = new SummaryView($this->di);
            return $view->main($item);
        }
    }

    /**
     * Export modal form.
     *
     * @return string
     * @throws Exception
     */
    public function exportformAction(): string {

        $this->authorization->signedId(true);

        $view = new ItemsView($this->di);
        return $view->exportForm(true);
    }
}
