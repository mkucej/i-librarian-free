<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Http\Message\StreamInterface;
use Librarian\Mvc\Controller;
use Librarian\Http\Client\Psr7;

/**
 * Class PageController
 *
 * Deals with PDF single page tasks. It is used by PDF viewer.
 */
class PageController extends Controller {

    /**
     * PageController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        // Authorization.
        $this->authorization->signedId(true);
    }

    /**
     * Main. Get PDF page.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        if (!isset($this->get['id'])) {

            throw new Exception("the parameter <kbd>id</kbd> is required", 400);
        }

        $this->validation->id($this->get['id']);

        if (!isset($this->get['number'])) {

            throw new Exception("the parameter <kbd>number</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['number'], 1, 100000);

        // Get icon.
        $model = new PageModel($this->di);
        $stream = $model->getPage($this->get['id'], $this->get['number']);

        // View.
        $view = new FileView($this->di, $stream);
        return $view->main();
    }

    /**
     * Empty page placeholder - white background.
     *
     * @return StreamInterface
     * @throws Exception
     */
    public function emptyAction(): string {

        // Get white page.
        try {

            $fp = Psr7\try_fopen(
                    IL_APP_PATH .
                    DIRECTORY_SEPARATOR . 'media' .
                    DIRECTORY_SEPARATOR . 'white.png', 'r'
            );

        } catch (Exception $exc) {

            $exc = null;
            throw new Exception('could not read file', 500);
        }

        // View.
        $view = new FileView($this->di, Psr7\stream_for($fp));
        return $view->main();
    }

    /**
     * Crop page and send image to client.
     *
     * @return string
     * @throws Exception
     */
    public function loadcropAction(): string {

        // Validate id.
        if (empty($this->get['id'])) {

            throw new Exception("Id required", 400);
        }

        $this->validation->id($this->get['id']);

        if (!isset($this->get['page'])) {

            throw new Exception("the parameter <kbd>page</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['page'], 1, 10000);

        if (!isset($this->get['x'])) {

            throw new Exception("the parameter <kbd>x</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['x'], 0, 10000);

        if (!isset($this->get['y'])) {

            throw new Exception("the parameter <kbd>y</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['y'], 0, 10000);

        if (!isset($this->get['width'])) {

            throw new Exception("the parameter <kbd>width</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['width'], 1, 10000);

        if (!isset($this->get['height'])) {

            throw new Exception("the parameter <kbd>height</kbd> is required", 400);
        }

        $this->validation->intRange($this->get['height'], 1, 10000);

        $model = new PageModel($this->di);

        $stream = $model->getCroppedPage(
            $this->get['id'],
            $this->get['page'],
            $this->get['x'],
            $this->get['y'],
            $this->get['width'],
            $this->get['height']
        );

        $view = new FileView($this->di, $stream);
        $view->filename = "img-{$this->get['id']}-p{$this->get['page']}-{$this->get['x']}-{$this->get['y']}.jpg";
        return $view->main('attachment');
    }

    /**
     * Crop and save image as a supplement.
     *
     * @return string
     * @throws Exception
     */
    public function savecropAction(): string {

        // Validate id.
        if (empty($this->post['id'])) {

            throw new Exception("Id required", 400);
        }

        $this->validation->id($this->post['id']);

        if (!isset($this->post['page'])) {

            throw new Exception("the parameter <kbd>page</kbd> is required", 400);
        }

        $this->validation->intRange($this->post['page'], 1, 10000);

        if (!isset($this->post['x'])) {

            throw new Exception("the parameter <kbd>x</kbd> is required", 400);
        }

        $this->validation->intRange($this->post['x'], 0, 10000);

        if (!isset($this->post['y'])) {

            throw new Exception("the parameter <kbd>y</kbd> is required", 400);
        }

        $this->validation->intRange($this->post['y'], 0, 10000);

        if (!isset($this->post['width'])) {

            throw new Exception("the parameter <kbd>width</kbd> is required", 400);
        }

        $this->validation->intRange($this->post['width'], 1, 10000);

        if (!isset($this->post['height'])) {

            throw new Exception("the parameter <kbd>height</kbd> is required", 400);
        }

        $this->validation->intRange($this->post['height'], 1, 10000);

        $model = new PageModel($this->di);

        $stream = $model->getCroppedPage(
            $this->post['id'],
            $this->post['page'],
            $this->post['x'],
            $this->post['y'],
            $this->post['width'],
            $this->post['height']
        );

        // Save as supplement.
        $model = new SupplementsModel($this->di);
        $model->save(
            $this->post['id'],
            $stream,
            "img-id{$this->post['id']}-p{$this->post['page']}-x{$this->post['x']}-y{$this->post['y']}-w{$this->post['width']}-h{$this->post['height']}.jpg"
        );

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'image was saved as a supplement']);
    }
}
