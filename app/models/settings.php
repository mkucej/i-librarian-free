<?php

namespace LibrarianApp;

use Exception;
use InvalidArgumentException;
use GuzzleHttp\Utils;

/**
 * Settings model.
 *
 * Magic methods:
 *
 * @method array loadGlobal()
 * @method array loadUser(string $id_hash)
 * @method void  saveGlobal(array $settings)
 * @method void  saveUser(array $settings)
 */
final class SettingsModel extends AppModel {

    /**
     * Save user settings.
     *
     * @param array $settings
     * @throws Exception
     */
    protected function _saveUser(array $settings): void {

        $sql1 = <<<EOT
DELETE
    FROM user_settings
    WHERE user_id = ? AND setting_name=?
EOT;

        $sql2 = <<<EOT
INSERT INTO user_settings
    (user_id, setting_name, setting_value)
    VALUES(?, ?, ?)
EOT;

        $this->db_main->beginTransaction();

        foreach ($settings as $key => $value) {

            $columns1 = [
                $this->user_id,
                $key
            ];

            $this->db_main->run($sql1, $columns1);

            if (is_array($value) === true) {

                $value = Utils::jsonEncode($value);
            }

            $columns2 = [
                $this->user_id,
                $key,
                $value
            ];

            $this->db_main->run($sql2, $columns2);
        }

        $this->db_main->commit();
    }

    /**
     * Load user settings.
     *
     * @param string $id_hash Id hash required here.
     * @return array
     */
    protected function _loadUser(string $id_hash) {

        $sql = <<<EOT
SELECT setting_name, setting_value
    FROM user_settings
    WHERE user_id = (SELECT id FROM users WHERE id_hash = ?)
EOT;

        $this->db_main->run($sql, [$id_hash]);

        $output = [];

        while ($row = $this->db_main->getResultRow()) {

            // Could be JSON data.
            try {

                $row['setting_value'] = Utils::jsonDecode($row['setting_value'], true);

            } catch (InvalidArgumentException $exc) {

                // Noop.
            }

            $output[$row['setting_name']] = $row['setting_value'];
        }

        return $output;
    }

    /**
     * Save global settings.
     *
     * @param  array $settings
     * @throws Exception
     */
    protected function _saveGlobal(array $settings): void {

        if ($this->permissions !== 'A') {

            throw new Exception('request requires administrator permissions', 403);
        }

        $this->db_main->beginTransaction();

        // Update global settings.
        $sql1 = <<<EOT
DELETE
    FROM settings
    WHERE setting_name=?
EOT;

        $sql2 = <<<EOT
INSERT INTO settings
    (setting_name, setting_value)
    VALUES(?, ?)
EOT;

        foreach ($settings as $key => $value) {

            $columns1 = [
                $key
            ];

            $this->db_main->run($sql1, $columns1);

            // Skip empty.
            if (empty($value)) {

                continue;
            }

            if (is_array($value) === true) {

                $value = Utils::jsonEncode($value);
            }

            $columns2 = [
                $key,
                $value
            ];

            $this->db_main->run($sql2, $columns2);
        }

        $this->db_main->commit();
    }

    /**
     * Load global settings.
     *
     * @return array
     * @throws Exception
     */
    protected function _loadGlobal() {

        $sql = <<<EOT
SELECT setting_name, setting_value
    FROM settings
EOT;

        $this->db_main->run($sql);

        $output = [];

        while ($row = $this->db_main->getResultRow()) {

            // Could be JSON data.
            try {

                $row['setting_value'] = Utils::jsonDecode($row['setting_value'], true);

            } catch (InvalidArgumentException $exc) {

                // Noop.
            }

            $output[$row['setting_name']] = $row['setting_value'];
        }

        return $output;
    }
}
