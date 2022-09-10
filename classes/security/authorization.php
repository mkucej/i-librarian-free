<?php

namespace Librarian\Security;

use Exception;

/**
 * Class to authorize controller actions.
 *
 * Important! Models must not use this authorization.
 */
final class Authorization {

    /**
     * @var Session
     */
    private Session $session;

    /**
     * Constructor.
     *
     * @param Session $session
     */
    public function __construct(Session $session) {

        $this->session = $session;
    }

    /**
     * Authorization amalgamation.
     *
     * @param  array $requirements
     * @return boolean
     * @throws Exception
     */
    public function authorize(array $requirements = []): bool {

        if (isset($requirements['signed_in'])) {

            return $this->signedId($requirements['signed_in']);
        }

        if (isset($requirements['permissions'])) {

            return $this->permissions($requirements['permissions']);
        }

        return false;
    }

    /**
     * Signed in the session.
     *
     * @param  boolean|string $requirement
     * @return boolean
     * @throws Exception
     */
    public function signedId($requirement): bool {

        switch ($requirement) {

            // Must be signed in.
            case true:
                if ($this->session->data('user_id') === null) {

                    // Sends 401 to client, which refreshes client views.
                    throw new Exception('session has expired, please sign in', 401);
                }
                return true;

            // Must not be signed in.
            case false:
                if ($this->session->data('user_id') !== null) {

                    throw new Exception('you must be signed out to complete this request', 403);
                }
                return true;

            // Can be signed in or not.
            case '*':
                return true;

            default:
                throw new Exception('session has expired, please sign in', 401);
        }
    }

    /**
     * Permissions.
     *
     * @param  string $requirement
     * @return boolean
     * @throws Exception
     */
    public function permissions(string $requirement): bool {

        switch ($requirement) {

            // Must be admin.
            case 'A':
                if ($this->session->data('permissions') !== 'A') {

                    throw new Exception('request requires admin permissions', 403);
                }
                return true;

            // Must be at least user.
            case '>U':
            case 'U':
                if ($this->session->data('permissions') !== 'A' && $this->session->data('permissions') !== 'U') {

                    throw new Exception('request requires at least user permissions', 403);
                }
                return true;

            // Must be at least guest.
            case '>G':
            case 'G':
            case '*':
                return true;

            default:
                throw new Exception('request requires admin permissions', 403);
        }
    }
}
