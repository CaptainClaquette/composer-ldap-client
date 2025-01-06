<?php

namespace hakuryo\ldap;

use Exception;
use hakuryo\ConfigParser\ConfigParser;
use hakuryo\ConfigParser\exceptions\FileNotFoundException;
use hakuryo\ConfigParser\exceptions\InvalidSectionException;
use hakuryo\ConfigParser\exceptions\UnsupportedFileTypeException;
use hakuryo\ldap\entities\exceptions\LDAPAddException;
use hakuryo\ldap\entities\exceptions\LDAPBindException;
use hakuryo\ldap\entities\exceptions\LDAPConnectException;
use hakuryo\ldap\entities\exceptions\LDAPDeleteException;
use hakuryo\ldap\entities\exceptions\LDAPModifyException;
use hakuryo\ldap\entities\exceptions\LDAPSearchException;
use hakuryo\ldap\entities\LdapSearchOptions;
use hakuryo\ldap\traits\ActiveDirectoryOperation;
use hakuryo\ldap\traits\LdapUtils;
use JsonException;
use stdClass;

use LDAP\Connection;

/**
 * Description of ConnectionLDAP
 *
 * @author Hakuryo
 */
class ConnectionLDAP
{

    use ActiveDirectoryOperation;
    use LdapUtils;

    const MANDATORY_KEY = ['USER', 'PWD', 'DN', 'HOST'];
    const USER_ACCOUNT_ENABLE = 0x0001;
    const USER_ACCOUNT_DISABLE = 0x0002;
    const USER_PASSWD_NOTREQD = 0x0020;
    const USER_PASSWD_CANT_CHANGE = 0x0040;
    const USER_NORMAL_ACCOUNT = 0x0200;
    const USER_DONT_EXPIRE_PASSWD = 0x10000;
    const USER_PASSWORD_EXPIRED = 0x800000;
    const ADS_SYSTEMFLAG_DISALLOW_DELETE = 0x80000000;

    const MOD_ADD = 0;
    const MOD_REPLACE = 1;
    const MOD_DEL = 2;

    public false|Connection $connection;
    private int $lastResultCount = 0;
    private LdapSearchOptions $searchOptions;

    /**
     * Create a instance of ConnectionLDAP
     * @param string $host The LDAP server fqdn
     * @param string $login The user's distinguished name
     * @param string $password The user's password
     * @param LdapSearchOptions|null $search_options an instance of LdapSearchOptions
     * @throws LDAPConnectException|LDAPBindException if LDAP server can't be conntacted or if the connection using user's credentials fail.
     */
    public function __construct(string $host, string $login, string $password, int $timeout, LdapSearchOptions $search_options = null)
    {
        ldap_set_option(null, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option(null, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
        if ($this->connection = ldap_connect($host)) {
            if (!@ldap_bind($this->connection, $login, $password)) {
                throw new LDAPBindException("Can't bind to ldap server $host cause : " . $this->getLastError(), -1);
            }
            $this->searchOptions = $search_options === null ? new LdapSearchOptions($this->getRootDN()) : $search_options;
        } else {
            throw new LDAPConnectException("Can't connect to ldap server $host cause : " . $this->getLastError(), -1);
        }
    }

    /**
     * Create a instance of ConnectionLDAP from a ini file.
     * The ini file must have HOST,USER,PWD keys
     * @param string $path Path of the ini file
     * @param string|null $section Section to use on the ini file.
     * @return ConnectionLDAP
     * @throws LDAPConnectException
     * @throws FileNotFoundException
     * @throws UnsupportedFileTypeException
     * @throws InvalidSectionException
     * @throws JsonException
     * @throws LDAPBindException
     */
    public static function fromFile(string $path, string $section = null): ConnectionLDAP
    {
        $conf = self::make_ldap_config(ConfigParser::parse($path, $section, self::MANDATORY_KEY));
        $ldap = new ConnectionLDAP($conf->host, $conf->user, $conf->pwd, $conf->timeout, new LdapSearchOptions($conf->base_dn));
        return $ldap;
    }

    /**
     * Perform a recursive search with $filter and retrieve $returnedAttrs attributs from baseDN level
     * @param string $filter The ldap filter.
     * @param array|string $returnedAttrs An array of string, or a comma separated string list of wanted attributes. Default = ['*'].
     * @param callable|null $callback function to execute when retrieving entry. Default = null.
     * @param string|null $trackBy replace the auto index key of the array by the value of the key for the corresponding line.
     * Tracking by "uid" will generate an array like so :
     * * array['value_of_uid'] = "corresponding entry"
     * @param int|null $pageSize Enable paginated search, and perform such search by $pageSize amount
     * @return array|null return null if no result with
     * @throws LDAPSearchException
     * @see ldap_search
     */
    public function search(string $filter, array|string $returnedAttrs = ['*'], callable $callback = null, string $trackBy = null, ?int $pageSize = null): array|null
    {
        $this->lastResultCount = 0;
        $attrs = gettype($returnedAttrs) === "array" ? $returnedAttrs : explode(',', $returnedAttrs);
        $entries = [];
        if ($pageSize != null) {
            $entries = $this->paginateSearch($filter, $attrs, $pageSize, $callback, $trackBy, true);
        } else {
            $research = @ldap_search($this->connection, $this->searchOptions->getBaseDN(), $filter, $attrs, 0, $this->searchOptions->getResultLimit());
            self::processResults($this, $research, $entries, $callback, $trackBy);
        }
        return count($entries) > 0 ? $entries : null;
    }

    /**
     * Perform a search with $filter and retrieve $returnedAttrs attributs on baseDN level only
     * @param string $filter The ldap filter.
     * @param array|string $returnedAttrs An array of string, or a comma separated string list of wanted attributes. Default = ['*'].
     * @param callable|null $callback function to execute when retrieving entry. Default = null.
     * @param string|null $trackBy replace the auto index key of the array by the value of the key for the corresponding line.
     * Tracking by "uid" will generate an array like so :
     * * array['value_of_uid'] = "corresponding entry"
     * @param int|null $pageSize Enable paginated search, and perform such search by $pageSize amount
     * @return array|null return null if no result with
     * @throws LDAPSearchException
     * @see ldap_search
     */
    public function list(string $filter, array|string $returnedAttrs = ['*'], callable $callback = null, string $trackBy = null, ?int $pageSize = null): array|null
    {
        $this->lastResultCount = 0;
        $attrs = gettype($returnedAttrs) === "array" ? $returnedAttrs : explode(',', $returnedAttrs);
        $entries = [];
        if ($pageSize != null) {
            $entries = $this->paginateSearch($filter, $attrs, $pageSize, $callback, $trackBy);
        } else {
            $research = ldap_list($this->connection, $this->searchOptions->getBaseDN(), $filter, $attrs, 0, $this->searchOptions->getResultLimit());
            self::processResults($this, $research, $entries, $callback, $trackBy);
        }
        return count($entries) > 0 ? $entries : null;
    }

    /**
     * Return the first entry matching $filter with attributes specified by $returnedAttrs.
     * @param string $filter The ldap filter.
     * @param array|string $returnedAttrs An array of string, or a comma separated string list of wanted attributes. Default = ['*'].
     * @param callable|null $callback function to execute when retrieving entry. Default = null.
     * @return stdclass|null return null if no result
     * @throws LDAPSearchException
     */
    public function getEntry(string $filter, array|string $returnedAttrs = ['*'], callable $callback = null): stdClass|null
    {
        $attrs = gettype($returnedAttrs) === "array" ? $returnedAttrs : explode(',', $returnedAttrs);
        if ($this->searchOptions->getScope() === LdapSearchOptions::SEARCH_SCOPE_SUB) {
            $research = ldap_search($this->connection, $this->searchOptions->getBaseDN(), $filter, $attrs, 0, 1);
        } else {
            $research = ldap_list($this->connection, $this->searchOptions->getBaseDN(), $filter, $attrs, 0, 1);
        }
        if (!$research) {
            throw new LDAPSearchException("Can't perform research cause : " . $this->getLastError());
        }
        $entry = ldap_first_entry($this->connection, $research);
        return $entry !== false ? self::clearEntry($this, $entry, $callback) : null;
    }

    /**
     * Modify a LDAP entry
     * @param string $entry_dn The distinguished name of the entry
     * @param array $target_entry_attr The attributes to modify and there values or a LdapBatchModification object
     * @param int $modify_type can be one of the constant MOD_ADD,MOD_DEL,MOD_REPLACE of ConnectionLDAP. default : MOD_REPLACE
     * @return bool Return True on success.
     * @throws LDAPModifyException throw an exception on fail.
     */
    public function modify(string $entry_dn, $target_entry_attr, int $modify_type = self::MOD_REPLACE): bool
    {
        $result = false;
        switch ($modify_type) {
            case self::MOD_ADD:
                $result = @ldap_mod_add($this->connection, $entry_dn, $target_entry_attr);
                break;
            case self::MOD_DEL:
                $result = @ldap_mod_del($this->connection, $entry_dn, $target_entry_attr);
                break;
            case self::MOD_REPLACE:
                $result = @ldap_mod_replace($this->connection, $entry_dn, $target_entry_attr);
                break;
        }
        if (!$result) {
            throw new LDAPModifyException("Can't performe modification of $entry_dn cause : " . $this->getLastError());
        }
        return $result;
    }

    /**
     * Create an LDAP entry
     * @param string $entry_dn The distinguished name of the entry
     * @param array $ldap_entry_attr the attributes of the entry
     * @return bool Return True on success.
     * @throws LDAPAddException throw an exception on fail.
     */
    public function add(string $entry_dn, array $ldap_entry_attr): bool
    {
        if (!ldap_add($this->connection, $entry_dn, $ldap_entry_attr)) {
            throw new LDAPAddException("Can't add ldap entry $entry_dn cause : " . $this->getLastError());
        }
        return true;
    }

    /**
     * Delete the ldap entry specified by $entry_dn
     * @param string $entry_dn The distinguished name of the entry
     * @return bool Return True on success.
     * @throws LDAPDeleteException throw an exception on fail.
     */
    public function delete(string $entry_dn): bool
    {
        if (!ldap_delete($this->connection, $entry_dn)) {
            throw new LDAPDeleteException("Can't delete ldap entry $entry_dn cause : " . $this->getLastError());
        }
        return true;
    }

    /**
     * Close the ldap connection
     */
    public function disconect(): void
    {
        ldap_close($this->connection);
    }

    // Getter & Setter
    public function getSearchOptions(): LdapSearchOptions
    {
        return $this->searchOptions;
    }

    private static function make_ldap_config($rawConf): stdClass
    {
        $config = new stdClass();
        $config->user = $rawConf->USER;
        $config->pwd = $rawConf->PWD;
        $config->base_dn = $rawConf->DN;
        $config->host = $rawConf->HOST;
        $config->timeout = property_exists($rawConf, 'TIMEOUT') ? $rawConf->TIMEOUT : 5;
        return $config;
    }

    private function paginateSearch(string $filter, array|string $returnedAttrs, int $pageSize, callable|null $callback, string|null $trackBy, bool $recursive = false): array|null
    {
        $controls = [];
        $cookie = '';
        $entries = [];
        do {
            if ($recursive) {
                $research = @ldap_search($this->connection, $this->searchOptions->getBaseDN(), $filter,
                    $returnedAttrs, 0, $this->searchOptions->getResultLimit(), 0, LDAP_DEREF_NEVER,
                    [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => $pageSize, 'cookie' => $cookie]]]
                );
            } else {
                $research = @ldap_list($this->connection, $this->searchOptions->getBaseDN(), $filter,
                    $returnedAttrs, 0, $this->searchOptions->getResultLimit(), 0, LDAP_DEREF_NEVER,
                    [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => $pageSize, 'cookie' => $cookie]]]
                );
            }
            ldap_parse_result(ldap: $this->connection, result: $research, error_code: $errCode, controls: $controls);
            self::processResults($this, $research, $entries, $callback, $trackBy);
            $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while (!empty($cookie));
        return $entries;
    }
}
