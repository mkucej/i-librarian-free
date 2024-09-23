<?php

namespace Librarian;

use Exception;
use Librarian\Container\DependencyInjector;
use GuzzleHttp\Utils;
use Librarian\Security\Session;

/**
 * AppSettings class has setters and getters for local/memory storage of
 * application setting.
 */
final class AppSettings {

    /**
     * @var DependencyInjector
     */
    private DependencyInjector $di;

    /**
     * Default user settings.
     */
    public array $default_user_settings = [
        'connect_arxiv'    => '1',
        'connect_crossref' => '1',
        'connect_xplore'   => '1',
        'connect_nasa'     => '1',
        'connect_ol'       => '1',
        'connect_patents'  => '1',
        'connect_pubmed'   => '1',
        'connect_pmc'      => '1',
        'connect_sciencedirect' => '1',
        'connect_scopus'   => '1',
        'custom_filename'  => ['author', '_', 'year', '_', 'title'],
        'dashboard_remove_search' => '0',
        'dashboard_remove_import' => '0',
        'dashboard_remove_items' => '0',
        'dashboard_remove_projects' => '0',
        'dashboard_remove_item_notes' => '0',
        'dashboard_remove_project_notes' => '0',
        'dashboard_remove_item_discussions' => '0',
        'dashboard_remove_project_discussions' => '0',
        'display_type'     => 'title',
        'icons_per_row'    => 'auto',
        'page_size'        => '10',
        'pdf_viewer'       => 'internal',
        'sorting'          => 'id',
        'theme'            => 'light',
        'timezone'         => 'UTC',
        'use_en_language'  => '0'
    ];

    /**
     * Default global settings.
     */
    public array $default_global_settings = [
        'api_crossref'        => '',
        'api_ieee'            => '',
        'api_nasa'            => '',
        'api_ncbi'            => '',
        'connection'          => 'direct',
        'custom1'             => 'Custom 1',
        'custom2'             => 'Custom 2',
        'custom3'             => 'Custom 3',
        'custom4'             => 'Custom 4',
        'custom5'             => 'Custom 5',
        'custom6'             => 'Custom 6',
        'custom7'             => 'Custom 7',
        'custom8'             => 'Custom 8',
        'custom_bibtex'       => ['author', '-', 'year', '-', 'title'],
        'default_permissions' => 'A',
        'disallow_signup'     => '0',
        'math_formatting'     => '0',
        'max_items'           => '10000',
        'proxy_name'          => '',
        'proxy_port'          => '',
        'proxy_username'      => '',
        'proxy_password'      => '',
        'proxy_auth'          => '',
        'proxy_pac'           => '',
        'soffice_path'        => '',
        'tesseract_path'      => '',
        'wpad_url'            => ''
    ];

    public array $extra_file_types = [
        'doc', 'docx', 'vsd', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'jpg', 'png'
    ];

    public array $extra_mime_types = [
        'application/zip', // Office documents are sometimes recognized as this.
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.visio',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'image/jpeg',
        'image/png'
    ];

    /**
     * @var array Array to hold app settings.
     */
    public array $settings;

    /**
     * Constructor.
     *
     * @param DependencyInjector $di
     */
    public function __construct(DependencyInjector $di) {

        $this->di = $di;
    }

    /**
     * Load settings from the ilibrarian.ini file. The ini file is always local.
     *
     * @throws Exception
     */
    public function loadIni(): void {

        $ini_file = is_readable(IL_CONFIG_PATH . DIRECTORY_SEPARATOR . "ilibrarian.ini") ?
            IL_CONFIG_PATH . DIRECTORY_SEPARATOR . "ilibrarian.ini" :
            IL_CONFIG_PATH . DIRECTORY_SEPARATOR . "ilibrarian-default.ini";

        if (is_readable($ini_file) === false) {

            throw new Exception("no ini file found", 500);
        }

        // Write ini settings to memory. No need to write to the session file.
        $this->settings['ini'] = parse_ini_file($ini_file, true);
    }

    /**
     * Get an INI setting. The ini file is always local.
     *
     * @param string|null $section
     * @param string|null $name
     * @return string|array
     * @throws Exception
     */
    public function getIni(string $section = null, string $name = null) {

        if (!isset($section)) {

            return $this->settings['ini'];

        } elseif (!isset($name)) {

            if (!isset($this->settings['ini'][$section])) {

                throw new Exception("ini settings section <kbd>$section</kbd> does not exist", 500);
            }

            return $this->settings['ini'][$section];

        } else {

            if (!isset($this->settings['ini'][$section][$name])) {

                throw new Exception("ini setting <kbd>$section&#9656;$name</kbd> does not exist", 500);
            }

            return $this->settings['ini'][$section][$name];
        }
    }

    /**
     * Get user settings.
     *
     * @param string|null $name
     * @return array|string
     * @throws Exception
     */
    public function getUser(string $name = null) {

        // If user settings were not saved to memory yet.
        if (!isset($this->settings['user'])) {

            /** @var Session $session */
            $session = $this->di->getShared('Session');

            // Read session. User settings must be saved in session during sign in.
            if ($session->data('settings') === null) {

                throw new Exception("user settings not saved in the session", 500);
            }

            $this->settings['user'] = $session->data('settings');
        }

        // Return requesting setting(s).
        if (!isset($name)) {

            return $this->settings['user'];

        } else {

            if (!isset($this->settings['user'][$name])) {

                // A new setting, sign the user out.
                throw new Exception("you will be signed out to experience upgraded I, Librarian", 401);
            }

            return $this->settings['user'][$name];
        }
    }

    /**
     * Set user setting. Save to session and memory.
     *
     * @param  array $settings Complete array of settings.
     * @throws Exception
     */
    public function setUser(array $settings): void {

        /** @var Session $session */
        $session = $this->di->getShared('Session');
        $session->data('settings', array_merge($this->default_user_settings, $settings));

        // Save to memory.
        $this->settings['user'] = array_merge($this->getUser(), $settings);
    }

    /**
     * Get global settings.
     *
     * @param string|null $name
     * @return array|string
     * @throws Exception
     */
    public function getGlobal(string $name = null) {

        // If global settings were not saved to memory yet.
        if (!isset($this->settings['global']) && is_readable(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json')) {

            $json_settings = file_get_contents(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json');
            $this->settings['global'] = Utils::jsonDecode($json_settings, JSON_OBJECT_AS_ARRAY);
        }

        // Return requested setting(s).
        if (!isset($name)) {

            return $this->settings['global'];

        } else {

            if (!isset($this->settings['global'][$name])) {

                // A new setting, delete stale settings.json, sign the user out.
                if (is_writable(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json') === true) {

                    unlink(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json');
                }

                throw new Exception("you will be signed out to experience upgraded I, Librarian", 401);
            }

            return $this->settings['global'][$name];
        }
    }

    /**
     * Set global settings. Save to json file and memory.
     *
     * @param  array $settings A complete array of settings.
     * @throws Exception
     */
    public function setGlobal(array $settings): void {

        $settings = array_merge($this->default_global_settings, $settings);

        $global_settings = Utils::jsonEncode($settings, JSON_PRETTY_PRINT);
        file_put_contents(IL_TEMP_PATH . DIRECTORY_SEPARATOR . 'settings.json', $global_settings, LOCK_EX);

        // Save to memory.
        $this->settings['global'] = array_merge($this->getGlobal(), $settings);
    }

    /**
     * Get proxy host and port.
     *
     * @return string|null
     * @throws Exception
     */
    public function proxyUrl() {

        // No proxy used.
        if ($this->getGlobal('connection') === 'direct') {

            return null;
        }

        // WPAD script used.
        if ($this->getGlobal('proxy_pac') !== '') {

            return $this->getGlobal('proxy_pac');
        }

        // Manual proxy settings used.
        if (empty($this->getGlobal('proxy_name'))) {

            return null;

        } else {

            $port = empty($this->getGlobal('proxy_port')) ? '' : ':' . $this->getGlobal('proxy_port');

            return $this->getGlobal('proxy_name') . $port;
        }
    }

    /**
     * Get proxy user:password string.
     *
     * @return string
     * @throws Exception
     */
    public function proxyUserPwd(): string {

        // Non-manual proxy settings.
        if ($this->getGlobal('connection') !== 'manual') {

            return '';
        }

        if (empty($this->getGlobal('proxy_username'))) {

            return '';

        } else {

            $user = rawurlencode($this->getGlobal('proxy_username'));
            $pass = rawurlencode($this->getGlobal('proxy_password'));

            return "$user:$pass";
        }
    }

    /**
     * Get proxy authentication type.
     *
     * @return int
     * @throws Exception
     */
    public function proxyAuthType(): int {

        if ($this->getGlobal('proxy_auth') === 'ntlm')  {

            return CURLAUTH_NTLM;

        } else {

            return CURLAUTH_BASIC;
        }
    }

    /**
     * Get API key.
     *
     * API keys can be defined in server conf as IL_API_NAME, or in global settings as api_name.
     *
     * @param string $name API key name.
     * @param array $server Server super globals.
     * @param bool $optional
     * @return string
     * @throws Exception
     */
    public function apiKey(string $name, array $server, bool $optional = false): string {

        // First, look in server globals.
        if (!empty($server['IL_API_' . strtoupper($name)])) {

            return $server['IL_API_' . strtoupper($name)];
        }

        // Second, look in global settings.
        try {

            if ($this->getGlobal('api_' . strtolower($name)) !== '') {

                return $this->getGlobal('api_' . strtolower($name));

            } elseif ($optional === false) {

                throw new Exception("API key <kbd>$name</kbd> not found. It must be set in the administrator settings.", 500);
            }

        } catch (Exception $exc) {

            if ($optional === false) {

                throw new Exception("API key <kbd>$name</kbd> not found. It must be set in the administrator settings.", 500);
            }
        }

        return '';
    }
}
