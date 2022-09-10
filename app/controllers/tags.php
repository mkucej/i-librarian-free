<?php

namespace LibrarianApp;

use Exception;
use Librarian\Container\DependencyInjector;

class TagsController extends AppController {

    /**
     * TagsController constructor.
     *
     * @param DependencyInjector $di
     * @throws Exception
     */
    public function __construct(DependencyInjector $di) {

        parent::__construct($di);

        $this->session->close();

        $this->authorization->signedId(true);
    }

    /**
     * Main is noop.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction() {

        $view = new DefaultView($this->di);
        return $view->main();
    }

    /**
     * Get tags for an item, including recommended tags.
     *
     * @return string
     * @throws Exception
     */
    public function itemAction(): string {

        // Check id.
        if (isset($this->get['id']) === false) {

            throw new Exception('id parameter required', 400);
        }

        $this->validation->id($this->get['id']);

        $model = new TagsModel($this->di);
        $tags = $model->getItemTags($this->get['id']);

        $view = new TagsView($this->di);
        return $view->item($this->get['id'], $tags);
    }

    /**
     * Add a tag to an item. Checkbox on.
     *
     * @return string
     * @throws Exception
     */
    public function additemAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Check ids.
        if (isset($this->post['id']) === false) {

            throw new Exception('id parameter required', 400);
        }

        $this->validation->id($this->post['id']);

        if (isset($this->post['tag_id']) === false) {

            throw new Exception('tag id parameter required', 400);
        }

        $this->validation->id($this->post['tag_id']);

        $model = new TagsModel($this->di);
        $model->saveItemTags($this->post['id'], (array) $this->post['tag_id']);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'item tag was added']);
    }

    /**
     * Remove a tag from item. Checkbox off. Does not delete the tag itself.
     *
     * @return string
     * @throws Exception
     */
    public function deleteitemAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Check ids.
        if (isset($this->post['id']) === false) {

            throw new Exception('id parameter required', 400);
        }

        $this->validation->id($this->post['id']);

        if (isset($this->post['tag_id']) === false) {

            throw new Exception('tag id parameter required', 400);
        }

        $this->validation->id($this->post['tag_id']);

        $model = new TagsModel($this->di);
        $model->deleteItemTag($this->post['id'], $this->post['tag_id']);

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'item tag was removed']);
    }

    /**
     * Create new tag. Optionally add to item id.
     *
     * @return string
     * @throws Exception
     */
    public function createAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Is there item id?
        $item_id = null;

        if (isset($this->post['id'])) {

            $this->validation->id($this->post['id']);

            $item_id = $this->post['id'];
        }

        $tags = explode("\n", $this->post['new_tags']);
        $tags = $this->sanitation->trim($tags);

        $model = new TagsModel($this->di);
        $model->createTag($tags, $item_id);

        if (isset($this->post['id'])) {

            $tags = $model->getItemTags($item_id);

            $view = new TagsView($this->di);
            return $view->item($item_id, $tags);
        }

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'new tag was created']);
    }

    /**
     * Tag management.
     *
     * @return string
     * @throws Exception
     */
    public function manageAction(): string {

        // Authorization.
        $this->authorization->permissions('U');

        $model = new TagsModel($this->di);
        $tags = $model->getTagCounts();

        $view = new TagsView($this->di);
        return $view->manage($tags);
    }

    /**
     * Edit tag name.
     *
     * @return string
     * @throws Exception
     */
    public function editAction(): string {

        // POST request is required.
        if ($this->request->getMethod() !== 'POST') {

            throw new Exception("request method must be POST", 405);
        }

        // Authorization.
        $this->authorization->permissions('U');

        if (isset($this->post['tag'])) {

            $this->validation->id(key($this->post['tag']));

        } else {

            throw new Exception("tag data missing", 400);
        }

        $model = new TagsModel($this->di);

        if (empty(current($this->post['tag']))) {

            $model->deleteTag(key($this->post['tag']));

        } else {

            $model->renameTag(key($this->post['tag']), current($this->post['tag']));
        }

        $view = new DefaultView($this->di);
        return $view->main(['info' => 'tag was updated']);
    }
}
