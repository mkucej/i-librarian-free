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
     * @param array $input
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

        // Empty list.
        if (count($items) === 0) {

            // Custom.
            if (strpos($type, 'custom') === 0) {

                $type = $this->app_settings->getGlobal($type);
            }

            /** @var Bootstrap\ListGroup $el */
            $el = $this->di->get('ListGroup');

            $el->addClass('list-group-flush');
            $el->div("no " . str_replace('_', ' ', $type) . ' found', 'border-0 text-center text-uppercase');
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
        $total_count = count($items);
        $half_count = (integer) ceil($total_count / 2);

        // Links start a new filter, so we are resetting to page #1.
        unset($sanitized_get['page']);
        // Remove filter search query.
        unset($sanitized_get['q']);

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
        $el = $this->di->get('ListGroup');

        $el->addClass('list-group-flush');

        // List #1.
        for ($i = 0; $i < $half_count; $i++) {

            // Add.
            $new_get = $sanitized_get;

            // Add filter to _GET.
            $new_get['filter'][$type][] = key($items);

            $get_query = '?'. http_build_query($new_get);

            $el->link("#{$controller}/filter{$get_query}", current($items), 'border-0');

            next($items);
        }

        $list_1 = $el->render();

        $el = null;

        /** @var Bootstrap\ListGroup $el */
        $el = $this->di->get('ListGroup');

        $el->addClass('list-group-flush');

        // List #2.
        for ($i = $half_count; $i < $total_count; $i++) {

            // Add.
            $new_get = $sanitized_get;

            // Add filter to _GET.
            $new_get['filter'][$type][] = key($items);

            $get_query = '?'. http_build_query($new_get);

            $el->link("#{$controller}/filter{$get_query}", current($items), 'border-0');

            next($items);
        }

        $list_2 = $el->render();

        $el = null;

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
