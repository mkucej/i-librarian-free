<?php

namespace LibrarianApp;

use DateTimeZone;
use Exception;
use Librarian\Container\DependencyInjector;
use Librarian\Mvc\Controller;

/**
 * Typeahead filters.
 *
 * @todo Do we want filter in collections?
 */
class FilterController extends Controller {

    /**
     * FilterController constructor.
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
     * Noop.
     *
     * @return string
     * @throws Exception
     */
    public function mainAction(): string {

        $view = new DefaultView($this->di);
        return $view->main([]);
    }

    /**
     * Authors.
     *
     * @return string
     * @throws Exception
     */
    public function authorAction(): string {

        $model = new AuthorsModel($this->di);

        if (empty($this->get['q'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            $items = $model->searchAuthors('library', $this->get['q']);

            $view = new FilterView($this->di);
            return $view->main($items);
        }
    }

    /**
     * Editors.
     *
     * @return string
     * @throws Exception
     */
    public function editorAction(): string {

        $model = new EditorsModel($this->di);

        if (empty($this->get['q'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            $items = $model->searchEditors('library', $this->get['q']);

            $view = new FilterView($this->di);
            return $view->main($items);
        }
    }

    /**
     * Publications.
     *
     * @param $type
     * @return string
     * @throws Exception
     */
    private function publication($type): string {

        $model = new PublicationtitlesModel($this->di);

        if (empty($this->get['q'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            $items = $model->search('library', $type, $this->get['q']);

            $view = new FilterView($this->di);
            return $view->main($items);
        }
    }

    /**
     * Primary titles.
     *
     * @return string
     * @throws Exception
     */
    public function primarytitleAction(): string {

        return $this->publication('primary_title');
    }

    /**
     * Secondary titles.
     *
     * @return string
     * @throws Exception
     */
    public function secondarytitleAction(): string {

        return $this->publication('secondary_title');
    }

    /**
     * Tertiary titles.
     *
     * @return string
     * @throws Exception
     */
    public function tertiarytitleAction(): string {

        return $this->publication('tertiary_title');
    }

    /**
     * Columns.
     *
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function column(string $type): string {

        $model = new ItemcolumnsModel($this->di);

        if (empty($this->get['q'])) {

            $view = new DefaultView($this->di);
            return $view->main();

        } else {

            $items = $model->search('library', $type, $this->get['q']);

            $view = new FilterView($this->di);
            return $view->main($items);
        }
    }

    /**
     * Custom 1.
     *
     * @return string
     * @throws Exception
     */
    public function custom1Action(): string {

        return $this->column('custom1');
    }

    /**
     * Custom 2.
     *
     * @return string
     * @throws Exception
     */
    public function custom2Action(): string {

        return $this->column('custom2');
    }

    /**
     * Custom 3.
     *
     * @return string
     * @throws Exception
     */
    public function custom3Action(): string {

        return $this->column('custom3');
    }

    /**
     * Custom 4.
     *
     * @return string
     * @throws Exception
     */
    public function custom4Action(): string {

        return $this->column('custom4');
    }

    /**
     * Custom 5.
     *
     * @return string
     * @throws Exception
     */
    public function custom5Action(): string {

        return $this->column('custom5');
    }

    /**
     * Custom 6.
     *
     * @return string
     * @throws Exception
     */
    public function custom6Action(): string {

        return $this->column('custom6');
    }

    /**
     * Custom 7.
     *
     * @return string
     * @throws Exception
     */
    public function custom7Action(): string {

        return $this->column('custom7');
    }

    /**
     * Custom 8.
     *
     * @return string
     * @throws Exception
     */
    public function custom8Action(): string {

        return $this->column('custom8');
    }

    /**
     * Filter an array of timezones.
     *
     * @return string
     * @throws Exception
     */
    public function timezoneAction(): string {

        // Timezones array.
        $timezones = DateTimeZone::listIdentifiers();
        $query = preg_quote($this->get['q'], '/');
        $tz_filtered = preg_grep("/.*{$query}.*/iu", $timezones);

        $view = new FilterView($this->di);
        return $view->main(array_values($tz_filtered));
    }

    /**
     * Filter tags.
     *
     * @return string
     * @throws Exception
     */
    public function tagAction(): string {

        $model = new TagsModel($this->di);
        $tags = $model->searchTags($this->get['q']);

        $view = new FilterView($this->di);
        return $view->main($tags);
    }

    /**
     * Filter citation styles.
     *
     * @return string
     * @throws Exception
     */
    public function citationAction(): string {

        $model = new CitationModel($this->di);
        $citations = $model->search($this->get['q']);

        $view = new FilterView($this->di);
        return $view->main($citations);
    }
}
