<?php

namespace Librarian\Security;

use Exception;
use FilesystemIterator;
use Librarian\AppSettings;

/**
 * Session class.
 */
final class Session {

    /**
     * @var AppSettings
     */
    private AppSettings $app_settings;

    /**
     * @var Encryption
     */
    private Encryption $encryption;

    /*
     * Scalars.
     */
    private int $lifetime = 604800;    // 604800 Session lifetime is 7 days.
    private string $save_path;         // Session save path.

    /**
     * Constructor.
     *
     * @param AppSettings $appSettings
     * @param Encryption $encryption
     * @throws Exception
     */
    public function __construct(AppSettings $appSettings, Encryption $encryption) {

        $this->app_settings = $appSettings;
        $this->encryption   = $encryption;

        // Session settings.
        $cookie_secure = $this->app_settings->getIni('session', 'cookie_secure');

        ini_set('session.name', 'IL');
        ini_set('session.cookie_path', parse_url(IL_BASE_URL, PHP_URL_PATH));
        ini_set('session.cookie_secure', (integer) $cookie_secure);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cache_limiter', '');

        // Custom session save path.
        $this->save_path = IL_DATA_PATH . DIRECTORY_SEPARATOR . 'sessions';
    }

    /**
     * Start the session.
     *
     * @throws Exception
     */
    public function start(): void {

        // Only start if session does not exist.
        if ($this->isActive() === true) {

            return;
        }

        // Session garbage collection first.
        $this->gc();

        // Custom session save path.
        session_save_path($this->save_path);

        // Custom session lifetime. Must be a long time, because PHP does not update it.
        session_set_cookie_params(2 * $this->lifetime);

        // Start a session.
        $started = session_start();

        if ($started === false) {

            throw new Exception('session could not be started', 500);
        }

        // Allow access to a previously destroyed session for a limited time (5 min.).
        if ($this->data('destroyed') !== null && $this->data('destroyed') < time() - 300) {

            $this->destroy();

            throw new Exception('session has expired, please reload', 401);
        }

        // Created time.
        if ($this->data('created') === null) {

            $this->data('created', time());
        }

        // Destroy session, if last access time longer than 7 days.
        if ($this->data('last_accessed') !== null && $this->data('last_accessed') < time() - $this->lifetime) {

            $this->destroy();

            throw new Exception('session has expired, please reload', 401);
        }

        // Set last access time.
        $this->data('last_accessed', time());

        // CSRF protection with a 256-bit/32-Byte key is built-in into session management.
        if ($this->data('token') === null) {

            $this->data('token', $this->encryption->getRandomKey(64));
        }
    }

    /**
     * Get or set session data for a resource path.
     *
     * @param string|null $name
     * @param string|array|null $value
     * @return string|array
     */
    public function data(string $name = null, $value = null) {

        // Dump everything.
        if (!isset($name)) {

            return $_SESSION['il'];
        }

        if (isset($value)) {

            // Setter.
            $_SESSION['il'][$name] = $value;
        }

        return $_SESSION['il'][$name] ?? null;
    }

    /**
     * Delete session data for a resource path.
     *
     * @param string $name
     */
    public function deleteData(string $name): void {

        unset($_SESSION['il'][$name]);
    }

    /**
     * Destroy the session.
     *
     * @return boolean
     * @throws Exception
     */
    public function destroy(): bool {

        // Unset all the session variables.
        $_SESSION = [];

        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Finally, destroy the session.
        $destroy = session_destroy();

        if ($destroy === false) {

            throw new Exception('could not end session', 500);
        }

        return true;
    }

    /**
     * Check if active session exists.
     *
     * @return boolean
     */
    public function isActive(): bool {

        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Custom session garbage collection.
     */
    private function gc(): void {

        if (rand(0, 9) === 5) {

            $it = new FilesystemIterator($this->save_path, FilesystemIterator::SKIP_DOTS);

            foreach ($it as $fileinfo) {

                if (time() - $fileinfo->getMTime() > $this->lifetime) {

                    unlink($fileinfo->getPathname());
                }
            }
        }
    }

    /**
     * Delete a session file. Use to log supernumerary sessions out.
     *
     * @param array $session_ids
     * @return void
     */
    public function deleteSessionFiles(array $session_ids) {

        foreach ($session_ids as $session_id) {

            $session_file = $this->save_path . DIRECTORY_SEPARATOR . 'sess_' . $session_id;

            if (is_writable($session_file)) {

                unlink($session_file);
            }
        }
    }

    /**
     * Read all session files for the logged-in user.
     *
     * File contents: il|serialized session array
     *
     * @param array $session_ids
     * @return array
     */
    public function readSessionFiles(array $session_ids): array {

        $output = [];

        foreach ($session_ids as $session_id) {

            $file = $this->save_path . DIRECTORY_SEPARATOR . "sess_$session_id";

            if (is_readable($file) === false) {

                continue;
            }

            $session_str = file_get_contents($file);
            $session_arr = unserialize(substr($session_str, 3));

            if (isset($session_arr['user_id']) && $session_arr['user_id'] === $this->data('user_id')) {

                $output[] = $session_arr;
            }
        }

        return $output;
    }

    /**
     * Regenerate session id. Prevents session id hijacking.
     */
    public function regenerateId(): void {

        // Set a destroyed flag to the expiring session.
        $this->data('destroyed', time());

        // Create new session id.
        session_regenerate_id();

        // Delete the copied destroyed flag.
        $this->deleteData('destroyed');
    }

    /**
     * Close the session.
     *
     * @return boolean
     */
    public function close(): bool {

        return session_write_close();
    }
}
