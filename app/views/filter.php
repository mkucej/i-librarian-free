<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap;
use Librarian\Html\Element;
use Librarian\Media\Temporal;
use Librarian\Mvc\TextView;

/**
 * Class FilterView
 *
 * Lists for filters.
 */
class FilterView extends TextView {

    /**
     * Typeahead menu.
     *
     * @param array|null $input
     * @return string
     * @throws Exception
     */
    public function main(array $input = null): string {

        // Empty.
        if (empty($input)) {

            $this->append(['html' => '']);
            return $this->send();
        }

        // Theme.
        $button_theme_classes = self::$theme === 'dark' ? 'bg-secondary text-white' : '';

        $buttons = '';

        foreach ($input as $key => $value) {

            /** @var Bootstrap\Button $el */
            $el = $this->di->get('Button');

            $el->addClass("dropdown-item rounded-0 $button_theme_classes");
            $el->role('option');
            $el->dataId($key);
            $el->html($value);
            $buttons .= $el->render();

            $el = null;
        }

        $this->append(['html' => "<div>$buttons</div>"]);

        return $this->send();
    }

    /**
     * Filter modals for collections.
     *
     * @param string $collection
     * @param string $type
     * @param array $items
     * @return string
     * @throws Exception
     */
    public function linkList(string $collection, string $type, array $items): string {

        // Format dates to local format.
        if ($type === 'added_time') {

            /** @var Temporal $temporal_obj */
            $temporal_obj = $this->di->get('Temporal');

            foreach ($items as $key => $item) {

                $items[$key] = $temporal_obj->toUserDate($item);
            }
        }

        // Sanitize _GET for links.
        $get = $this->request->getQueryParams();
        $trimmed_get = $this->sanitation->trim($get);
        $sanitized_get = $this->sanitation->urlquery($trimmed_get);
        $sanitized_get = $this->sanitation->html($sanitized_get);
        // Links start a new filter, so we are resetting to page #1.
        unset($sanitized_get['page']);
        // Remove filter search query.
        unset($sanitized_get['q']);

        $total_count = count($items);

        // Empty list.
        if ($total_count === 0) {

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->addClass('list-group-flush');
            $el->div($this->lang->t9n('no items'), 'border-0 text-center text-uppercase text-secondary');
            $list = $el->render();

            $el = null;

            /** @var Bootstrap\Row $el */
            $el = $this->di->get('Row');

            $el->addClass('no-gutters');
            $el->column($list);
            $row = $el->render();

            $el = null;

            /** @var Element $el */
            $el = $this->di->get('Element');

            $el->addClass('container-fluid p-0');
            $el->html($row);
            $container = $el->render();

            $el = null;

            $this->append(['html' => $container]);

            return $this->send();
        }

        // Populated lists.
        switch ($collection) {

            case 'library':
                $controller = 'items';
                break;

            case 'clipboard':
                $controller = 'clipboard';
                break;

            case 'project':
                $controller = 'project';
                break;

            default:
                $controller = 'items';
        }

        /** @var Bootstrap\ListGroup $el */
        $el1 = $this->di->get('ListGroup');

        /** @var Bootstrap\ListGroup $el */
        $el2 = $this->di->get('ListGroup');

        $el1->addClass('list-group-flush');
        $el2->addClass('list-group-flush');

        // Untagged link with mock id 0.
        if ($type === 'tag') {

            $new_get = $sanitized_get;
            $new_get['filter']['tag'][] = 0;
            $get_query = '?'. http_build_query($new_get);

            $el1->link("#{$controller}/filter{$get_query}", '!' . $this->lang->t9n('untagged'), 'border-0');
        }

        $max_items = $type === 'tag' ? $total_count : min($total_count, 99);

        for ($i = 0; $i < $max_items; $i++) {

            // Add filter to _GET.
            $new_get = $sanitized_get;
            $new_get['filter'][$type][] = key($items);
            $get_query = '?'. http_build_query($new_get);

            if ($i % 2 === 0) {

                $el1->link("#{$controller}/filter{$get_query}", current($items), 'border-0');

            } else {

                $el2->link("#{$controller}/filter{$get_query}", current($items), 'border-0');
            }

            next($items);
        }

        // Add ellipses.
        if ($total_count > 99) {

            $el2->link('#', '&hellip;', 'border-0');
        }

        $list_1 = $el1->render();
        $list_2 = $el2->render();

        $el1 = null;
        $el2 = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->addClass('no-gutters');
        $el->column($list_1);
        $el->column($list_2);
        $row = $el->render();

        $el = null;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('container-fluid p-0');
        $el->append($row);
        $container = $el->render();

        $el = null;

        $this->append(['html' => $container]);

        return $this->send();
    }
}
