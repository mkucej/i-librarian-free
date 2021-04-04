<?php

namespace Librarian;

final class Installation {

    private $model;

    public function __construct(InstallationModel $model) {

        $this->model = $model;
    }

    /**
     * Execute all installation actions.
     */
    public function install(): void {

        // Create folders in data.
        $this->model->createFolders();

        // Create database tables.
        $this->model->createTables();
    }

    /**
     * Upgrade.
     */
    public function upgrade(): void {

        // Create reference type index.
        $this->model->createReferenceTypeIndex();
    }
}
