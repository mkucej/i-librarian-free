<?php

namespace Librarian\Html\Bootstrap;

/**
 * Bootstrap's alert.
 */
final class Alert extends Component {

    private $dismissable;

    private $heading;

    /**
     * Constructor.
     */
    public function __construct() {

        parent::__construct();

        $this->addClass('alert bg-transparent rounded-0 d-flex align-items-center justify-content-between py-4');
    }

    /**
     * Set/get heading.
     *
     * @param  string $heading
     * @return string
     */
    public function heading(string $heading = null): string {

        if (isset($heading)) {

            $this->heading = $heading;
        }

        return $this->heading;
    }

    /**
     * Set/get dismissable flag.
     *
     * @param  boolean $dismissable
     * @return boolean
     */
    public function dismissable(bool $dismissable = null): bool {

        if (isset($dismissable)) {

            $this->dismissable = $dismissable;
        }

        return $this->dismissable;
    }

    /**
     * Render the component.
     *
     * @return string
     */
    public function render(): string {

        // Context.
        if (!empty($this->context)) {

            $this->addClass("text-{$this->context} border-{$this->context}");
        }

        // Heading.
        $heading = '';

        if (!empty($this->heading)) {

            $heading = "<h5 class=\"alert-heading text-uppercase\"><b>{$this->heading}</b></h5>";
        }

        $this->html(<<<EOT
            <span class="d-inline-block mr-3 mdi mdi-alert-decagram-outline mdi-36px pb-1"></span>
            <div class="alert-content w-100">{$heading}{$this->html()}</div>
EOT
        );

        // Dismissable.
        if ($this->dismissable === true) {

            $this->append(<<<'EOT'
                <button type="button" class="close ml-3" data-dismiss="alert" aria-label="Close alert">
                    <span class="mdi mdi-close" aria-hidden="true"></span>
                </button>
EOT
            );
        }

        return "{$this->startTag()}{$this->html()}{$this->endTag()}";
    }
}
