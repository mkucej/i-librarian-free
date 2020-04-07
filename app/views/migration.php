<?php

namespace LibrarianApp;

use Exception;
use Librarian\Html\Bootstrap as Bootstrap;
use Librarian\Html\Element;
use Librarian\Mvc\TextView;

class MigrationView extends TextView {

    /**
     * Main.
     *
     * @return string
     * @throws Exception
     */
    public function main() {

        /*
         * Head.
         */

        $this->title('Upgrade');

        $this->styleLink('css/plugins.css');

        $this->head();

        /*
         * Body.
         */

        /** @var Bootstrap\Breadcrumb $el */
        $el = $this->di->get('Breadcrumb');

        $el->style('margin: 0 -15px');
        $el->item('IL', IL_BASE_URL);
        $el->item("Upgrade");
        $bc = $el->render();

        $el = null;

        // OS-specific path example.
        if (DIRECTORY_SEPARATOR === "\\") {

            $example_path = "C:\\I,&nbsp;Librarian\\library";

        } else {

            $example_path = "/var/lib/i-librarian/library";
        }

        /** @var Bootstrap\Input $el */
        $el = $this->di->get('Input');

        $el->name('directory');
        $el->label('The location of "library" directory');
        $el->required('required');
        $el->hint(<<<EOT
            <p>
                Enter a path to your library directory, e.g.
                <code>{$example_path}</code>. Requires version >3.6.
                Make sure you have enough free space on your hard drive,
                because the legacy library files are copied to the new location.
            </p>
EOT
            );
        $location = $el->render();

        $el = null;

        /** @var Bootstrap\Button $el */
        $el = $this->di->get('Button');

        $el->context('danger');
        $el->type('submit');
        $el->html('Upgrade');
        $upgrade_button = $el->render();

        $el = null;

        /** @var Bootstrap\Form $el */
        $el = $this->di->get('Form');

        $el->method('GET');
        $el->action(IL_BASE_URL . 'index.php/migration/legacyupgrade');
        $el->id('migrate-form');
        $el->append($location . $upgrade_button);
        $card_content = $el->render();

        $el = null;

        /** @var Bootstrap\Card $el */
        $el = $this->di->get('Card');

        $el->addClass('bg-white mt-4');
        $el->header('<b>UPGRADE LEGACY I,&nbsp;LIBRARIAN</b>');
        $el->body($card_content);
        $card = $el->render();

        $el = null;

        /** @var Bootstrap\Row $el */
        $el = $this->di->get('Row');

        $el->column($card, 'col-lg-4 offset-lg-4');
        $content = $el->render();

        $el = null;

        /** @var Element $el */
        $el = $this->di->get('Element');

        $el->addClass('container-fluid');
        $el->append($bc . $content);
        $container = $el->render();

        $el = null;

        $this->append($container);

        /*
         * End.
         */

        $this->scriptLink('js/plugins.js');

        $this->script(<<<EOT
            $(function(){
                new MigrationView();
                $('[data-toggle="tooltip"]').tooltip();
            });
EOT
        );

        $this->end();

        return $this->send();
    }
}
