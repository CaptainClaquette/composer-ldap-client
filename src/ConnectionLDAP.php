<?php

namespace hakuryo\ldap;

use Exception;
use hakuryo\ldap\exceptions\LDAPAddException;
use hakuryo\ldap\exceptions\LDAPBindException;
use hakuryo\ldap\exceptions\LDAPConnectException;
use hakuryo\ldap\exceptions\LDAPDeleteException;
use hakuryo\ldap\exceptions\LDAPModifyException;
use hakuryo\ldap\exceptions\LDAPSearchException;
use hakuryo\ldap\LdapBatchModification as LdapLdapBatchModification;
use hakuryo\ldap\traits\ActiveDirectoryOperation;
use hakuryo\ldap\utils\ConfigParser;
use JsonException;
use LDAP\ResultEntry;
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
    public ?string $name;
    public ?string $entryClass = \stdClass::class;
    private $searchOptions;

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
     * @param string $section Section to use on the ini file.
     * @return \hakuryo\ldap\ConnectionLDAP
     * @throws Exception
     * @throws JsonException
     */
    public static function fromFile(string $path, string $section = null): ConnectionLDAP
    {
        $conf = ConfigParser::parseConfigFile($path, $section);
        $ldap = new ConnectionLDAP($conf->host, $conf->user, $conf->pwd, $conf->timeout, new LdapSearchOptions($conf->base_dn));
        $ldap->name = $conf->name;
        return $ldap;
    }

    /**
     * Retourne le resultat de $filter avec les attribut specifie dans $returnedAttrs
     * en faisant une recherhc recursive à partir du base_dn
     * @param String $filter Filtre ldap
     * @param array $returnedAttrs [Optionnel] Tableau d'attribut a retourner. Defaut = ['*']
     * @return array Tableau associatif avec les noms d'attributs ldap en tant que clef.
     * @see ldap_search
     */
    public function search(string $filter, array $returnedAttrs = ['*']): array
    {
        $entrys = [];
        $research = @ldap_search($this->connection, $this->searchOptions->getBaseDN(), $filter, $returnedAttrs, 0, $this->searchOptions->getResultLimit());
        self::processResults($this, $research, $entrys);
        return $entrys;
    }

    /**
     * Retourne le resultat de $filter avec les attribut specifie dans $returnedAttrs
     * en faisant une recherche dans l'ou base_dn uniquement
     * @param String $filter Filtre ldap
     * @param array $returnedAttrs [Optionnel] Tableau d'attribut a retourner. Defaut = ['*']
     * @return array Tableau associatif avec les noms d'attributs ldap en tant que clef.
     * @see ldap_list
     */
    public function list(string $filter, array $returnedAttrs = ['*']): array
    {
        $entrys = [];
        $research = ldap_list($this->connection, $this->searchOptions->getBaseDN(), $filter, $returnedAttrs, 0, $this->searchOptions->getResultLimit());
        self::processResults($this, $research, $entrys);
        return $entrys;
    }

    /**
     * Retourne l'entree corespondant a $filter avec les attributs specifie dans $returnedAttrs.
     * @param string $filter le filtre LDAP
     * @param array $returnedAttrs [Optionnel] Tableau d'attribut a retourner. Defaut = ['*']
     * @return stdclass retourne un stdClass vide si aucun resultat ne correspond a $filter
     * @throws LDAPSearchException
     */
    public function getEntry(string $filter, array $returnedAttrs = ['*']): stdClass|null
    {
        if ($this->searchOptions->getScope() === LdapSearchOptions::SEARCH_SCOPE_SUB) {
            $research = ldap_search($this->connection, $this->searchOptions->getBaseDN(), $filter, $returnedAttrs, 0, 1);
        } else {
            $research = ldap_list($this->connection, $this->searchOptions->getBaseDN(), $filter, $returnedAttrs, 0, 1);
        }
        if (!$research) {
            throw new LDAPSearchException("Can't perform research cause : " . $this->getLastError());
        }
        $entry = ldap_first_entry($this->connection, $research);
        return $entry !== false ? self::clearEntry($this, $entry) : null;
    }

    /**
     * Modify a LDAP entry
     * @param string $entry_dn The distinguished name of the entry
     * @param array $target_entry_attr The attributes to modify and there values or a LdapBatchModification object
     * @param int $modify_type can be one of the constant MOD_ADD,MOD_DEL,MOD_REPLACE of ConnectionLDAP. default : MOD_REPLACE
     * @return bool Return True on success.
     * @throws Exception throw an exception on fail.
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
    public function disconect()
    {
        ldap_close($this->connection);
    }

    // Getter & Setter
    public function getSearchOptions(): LdapSearchOptions
    {
        return $this->searchOptions;
    }
}
