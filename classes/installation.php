<?php

namespace Librarian;

use Exception;

final class Installation {

    private InstallationModel $model;

    public function __construct(InstallationModel $model) {

        $this->model = $model;
    }

    /**
     * Execute all installation actions.
     * @throws Exception
     */
    public function install(): void {

        // Create folders in data.
        $this->model->createFolders();

        // Create database tables.
        $this->model->createTables();
    }

    /**
     * Upgrade.
     *
     * @throws Exception
     */
    public function upgrade(): void {

        // Create reference type index.
        $this->model->createReferenceTypeIndex();

        // Update items_authors primary index.
        $this->model->updateItemsAuthorsPrimaryIndex();
    }
}
