<?php

namespace Librarian\Security;

use Exception;
use Librarian\AppSettings;

/**
 * LDAP authentication code.
 */
final class Ldap {

    /**
     * @var resource LDAP connection
     */
    private $ldap_connect;

    /**
     * @var Validation
     */
    private $validation;

    /**
     * @var array LDAP ini settings.
     */
    private $settings;

    /**
     * Ldap constructor.
     *
     * @param Validation $validation
     * @param AppSettings $app_settings
     * @throws Exception
     */
    public function __construct(Validation $validation, AppSettings $app_settings) {

        $this->validation = $validation;
        $this->settings = $app_settings->getIni('ldap');
    }

    /**
     * Authenticate LDAP users.
     *
     * This is mostly a copy of the procedural code from I, Librarian v4.
     *
     * @param  string $username
     * @param  string $password
     * @return array
     * @throws Exception
     */
    public function authenticate(string $username, string $password): array {

        // Prevent LDAP injection attack.
        $this->validation->ldap($username);

        // Verify if ldap was enabled within php.
        if (function_exists("ldap_connect") === false) {

            throw new Exception('PHP LDAP extension is not installed', 500);
        }

        // Set LDAP debug level
        if ($this->settings['ldap_debug_enabled'] === '1') {

            if (!ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, $this->settings['ldap_opt_debug_level'])) {

                throw new Exception('failed to set LDAP debug level', 500);
            }
        }

        // Connect.
        $this->ldap_connect = ldap_connect($this->settings['ldap_server']);

        if ($this->ldap_connect === false) {

            throw new Exception('could not connect to the specified LDAP server', 500);
        }

        // Upgrade to TLS for ldap connections.
        if (parse_url($this->settings['ldap_server'], PHP_URL_SCHEME) === 'ldap' && $this->settings['ldap_use_tls'] === '1') {

            $tls_on = ldap_start_tls($this->ldap_connect);

            if ($tls_on === false) {

                throw new Exception('unable to establish secure connection the LDAP server', 500);
            }
        }

        // Set LDAP version.
        if (!ldap_set_option($this->ldap_connect, LDAP_OPT_PROTOCOL_VERSION, $this->settings['ldap_version'])) {

            throw new Exception('failed to set LDAP protocol version', 500);
        }

        // Enable referrals.
        if (!ldap_set_option($this->ldap_connect, LDAP_OPT_REFERRALS, $this->settings['ldap_opt_referrals'])) {

            throw new Exception('failed to set referrals option', 500);
        }

        // Bind proxy user.
        $ldap_bind = ldap_bind($this->ldap_connect, $this->settings['ldap_binduser_dn'], $this->settings['ldap_binduser_pw']);

        if ($ldap_bind === false) {

            throw new Exception('failed to bind proxy user', 500);
        }

        /*
         * Lookup user.
         * Users matching the following criteria are eligible:
         * - must be a person object of class user or iNetOrgPerson
         * - however, the default user filter can be redefined with the ldap_user_filter setting
         * - username must match the CN attribute specified in INI file
         * - must be situated below the base search DN
         */
        if (isset($this->settings['ldap_user_filter']) === false || $this->settings['ldap_user_filter'] === '') {

            $ldap_lookup = "(&(|(objectClass=user)(objectClass=iNetOrgPerson))({$this->settings['ldap_username_attr']}={$username}))";

        } else {

            $ldap_lookup ="(&" . $this->settings['ldap_user_filter'] . "({$this->settings['ldap_username_attr']}={$username}))";
        }

        $ldap_sr = ldap_search(
            $this->ldap_connect,
            $this->settings['ldap_basedn'],
            $ldap_lookup,
            [$this->settings['ldap_username_attr']]
        );

        if ($ldap_sr === false) {

            throw new Exception('user search failed', 500);
        }

        // Get the user's DN.
        $ldap_num_entries = ldap_count_entries($this->ldap_connect, $ldap_sr);

        if ($ldap_num_entries !== 1) {

            throw new Exception('account does not exist', 403);
        }

        $ldap_user_sr = ldap_first_entry($this->ldap_connect, $ldap_sr);
        $ldap_user_dn = ldap_get_dn($this->ldap_connect, $ldap_user_sr);

        // Fix characters in ldap_user_dn https://msdn.microsoft.com/en-us/library/aa746475(v=vs.85).aspx
        $search_chars  = ["*", "(", ")", "\\", "/"];
        $replace_chars = ["\\2a", "\\28", "\\29", "\\5c", "\\2f"];
        $ldap_user_dn  = str_replace($search_chars,$replace_chars, $ldap_user_dn);

        /*
         * Optional authorization. If there are no groups, or list of admins, all users will be admins.
         */
        if (!empty($this->settings['ldap_admingroup_cn']) || !empty($this->settings['ldap_admingroup_dn'])) {

            $permissions = $this->authorize($ldap_user_dn);

        } elseif (isset($this->settings['ldap_admin_users']) === true && $this->settings['ldap_admin_users'] !== '') {

            $admins = explode(',', $this->settings['ldap_admin_users']);

            $permissions = 'U';

            for ($i = 0; $i < count($admins); $i++) {

                if ($username == trim($admins[$i]) ) {

                    $permissions = 'A';
                }
            }

        } else {

            $permissions = 'A';
        }

        /*
         * Finally, check the password of the user.
         */

        // Get all user attributes.
        $ldap_sr_all_user_attributes = @ldap_search(
            $this->ldap_connect,
            $this->settings['ldap_basedn'],
            $ldap_lookup,
            [$this->settings['ldap_username_attr'], 'givenName', 'sn', 'mail']
        );

        $usersattributes = ldap_get_entries($this->ldap_connect, $ldap_sr_all_user_attributes);

        // Try to connect to ldap using the given attribute and the password.
        try {

            $ldap_bind_check_pass = ldap_bind($this->ldap_connect, $usersattributes[0]['dn'], $password);

        } catch ( Exception $e ) {

            $ldap_bind_check_pass = false;
        }

        ldap_close($this->ldap_connect);

        if ($ldap_bind_check_pass === false) {

            throw new Exception('incorrect password', 403);
        }

        // First name.
        if (isset($usersattributes[0]['GivenName'][0])) {

            $first_name = $usersattributes[0]['GivenName'][0];

        } elseif (isset($usersattributes[0]['givenName'][0])) {

            $first_name = $usersattributes[0]['givenName'][0];

        } elseif (isset($usersattributes[0]['givenname'][0])) {

            $first_name = $usersattributes[0]['givenname'][0];

        } else {

            $first_name = '';
        }

        // Return the data to update local database.
        return [
            'username'    => $username,
            'first_name'  => $first_name,
            'last_name'   => isset($usersattributes[0]['sn'][0]) ? $usersattributes[0]['sn'][0] : '',
            'email'       => isset($usersattributes[0]['mail'][0]) ? $usersattributes[0]['mail'][0] : '',
            'permissions' => $permissions
        ];
    }

    /**
     * Authorization part.
     *
     * @param  string $ldap_user_dn
     * @return string Permissions based on group affiliation.
     * @throws Exception
     */
    private function authorize(string $ldap_user_dn): string {

        // Compile admin group DNs.
        if (empty($this->settings['ldap_admingroup_dn'])) {

            if (!empty($this->settings['ldap_admingroup_cn'])) {

                $admin_parts[] = $this->settings['ldap_admingroup_cn'];
            }

            if (!empty($this->settings['ldap_group_rdn'])) {

                $admin_parts[] = $this->settings['ldap_group_rdn'];
            }

            $admin_parts[] = $this->settings['ldap_basedn'];
            $ldap_admingroup_dn = join(',', $admin_parts);

        } else {

            $ldap_admingroup_dn = $this->settings['ldap_admingroup_dn'];
        }

        // Search user in admin group.
        $ldap_sr = @ldap_read(
            $this->ldap_connect,
            $ldap_admingroup_dn,
            "({$this->settings['ldap_filter']}={$ldap_user_dn})",
            ['member']
        );

        $ldap_info_group = @ldap_get_entries($this->ldap_connect, $ldap_sr);

        if ($ldap_info_group['count'] > 0) {

            // This user is admin.
            return 'A';
        }

        // Compile user group DNs.
        if (!empty($this->settings['ldap_usergroup_cn'])) {

            if (!empty($this->settings['ldap_usergroup_cn'])) {

                $user_parts[] = $this->settings['ldap_usergroup_cn'];
            }

            if (!empty($this->settings['ldap_group_rdn'])) {

                $user_parts[] = $this->settings['ldap_group_rdn'];
            }

            $user_parts[] = $this->settings['ldap_basedn'];
            $ldap_usergroup_dn = join(',', $user_parts);

        } elseif (!empty($this->settings['ldap_usergroup_dn'])) {

            $ldap_usergroup_dn = $this->settings['ldap_usergroup_dn'];

        } else {

            $ldap_usergroup_dn = '';
        }

        /*
         * If we don't have a ldap_usergroup_dn setting, assume all
         * users under the search base are eligible.
         */
        if (empty($ldap_usergroup_dn)) {

            return 'U';
        }

        // Search user in the specified group.
        $ldap_sr = @ldap_read(
            $this->ldap_connect,
            $ldap_usergroup_dn,
            "({$this->settings['ldap_filter']}={$ldap_user_dn})",
            ['member']
        );

        $ldap_info_group = @ldap_get_entries($this->ldap_connect, $ldap_sr);

        if ($ldap_info_group['count'] > 0) {

            return 'U';
        }

        throw new Exception('you are not authorized to use this software', 403);
    }
}
