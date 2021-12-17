<?php

namespace hakuryo\ldap;

use Exception;
use hakuryo\ldap\LdapBatchModification as LdapLdapBatchModification;
use hakuryo\ldap\traits\ActiveDirectoryOperation;
use hakuryo\ldap\utils\ConfigParser;
use JsonException;

/**
 * Description of ConnectionLDAP
 *
 * @author Hakuryo
 */
class ConnectionLDAP
{

    use ActiveDirectoryOperation;
    use LdapUtils;

    const MOD_ADD = 0;
    const MOD_REPLACE = 1;
    const MOD_DEL = 2;


    /**
     * AD user_account_control value
     */
    const USER_ACCOUNT_ENABLE = 0x0001;

    /**
     * AD user_account_control value
     */
    const USER_ACCOUNT_DISABLE = 0x0002;

    /**
     * AD user_account_control value
     */
    const USER_PASSWD_NOTREQD = 0x0020;

    /**
     * AD user_account_control value
     */
    const USER_PASSWD_CANT_CHANGE = 0x0040;

    /**
     * AD user_account_control value
     */
    const USER_NORMAL_ACCOUNT = 0x0200;

    /**
     * AD user_account_control value
     */
    const USER_DONT_EXPIRE_PASSWD = 0x10000;

    /**
     * AD user_account_control value
     */
    const USER_PASSWORD_EXPIRED = 0x800000;

    /**
     * AD user_account_control value
     */
    const ADS_SYSTEMFLAG_DISALLOW_DELETE = 0x80000000;

    public $connection;
    private $search_options;

    /**
     * Create a instance of ConnectionLDAP
     * @param string $host The LDAP server fqdn
     * @param string $login The user's distinguished name 
     * @param string $password The user's password
     * @param \hakuryo\ldap\LdapSearchOptions $search_options an instance of LdapSearchOptions
     * @throws Exception if LDAP server can't be conntacted or if the connection using user's credentials fail.
     */
    public function __construct(string $host, string $login, string $password, LdapSearchOptions $search_options = null)
    {
        if ($this->connection = ldap_connect($host)) {
            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            if (!@ldap_bind($this->connection, $login, $password)) {
                throw new Exception("Can't bind to ldap server $host cause : " . $this->getLastError(), -1);
            }
            $this->search_options = $search_options === null ? new LdapSearchOptions($this->get_root_dn()) : $search_options;
        } else {
            throw new Exception("Can't connect to ldap server $host cause : " . $this->getLastError(), -1);
        }
    }

    /**
     * Create a instance of ConnectionLDAP from a ini file.
     * The ini file must have HOST,USER,PWD keys
     * @param string $path Path of the ini file
     * @param string $section Section to use on the ini file.
     * @throws JsonException
     * @throws Exception
     * @return \hakuryo\ldap\ConnectionLDAP
     */
    public static function from_file(string $path, string $section = null): ConnectionLDAP
    {
        $conf = ConfigParser::parse_config_file($path, $section);
        $ldap = new ConnectionLDAP($conf->host, $conf->user, $conf->pwd, new LdapSearchOptions($conf->base_dn));
        return $ldap;
    }

    /**
     * Retourne le resultat de $filter avec les attribut specifie dans $returnedAttrs.
     * @param String $filter Filtre ldap
     * @param array $returnedAttrs [Optionnel] Tableau d'attribut a retourner. Defaut = ['*']
     * @see ldap_search
     * @return array Tableau associatif avec les noms d'attributs ldap en tant que clef.
     */
    public function search(string $filter, array $returnedAttrs = ['*']): array
    {
        $research = false;
        if ($this->search_options->get_scope() === LdapSearchOptions::SEARCH_SCOPE_SUB) {
            $research = @ldap_search($this->connection, $this->search_options->get_base_dn(), utf8_encode($filter), $returnedAttrs, 0, $this->search_options->get_result_limit());
        } else {
            $research = @ldap_list($this->connection, $this->search_options->get_base_dn(), utf8_encode($filter), $returnedAttrs, 0, $this->search_options->get_result_limit());
        }
        if (!$research) {
            throw new Exception("Can't perform research cause : " . $this->getLastError());
        }
        $entrys = @ldap_get_entries($this->connection, $research);
        return $entrys['count'] > 0 ? $this->clear_ldap_result($entrys) : [];
    }

    /**
     * Retourne l'entree corespondant a $filter avec les attributs specifie dans $returnedAttrs.
     * @param string $filter le filtre LDAP
     * @param array $returnedAttrs [Optionnel] Tableau d'attribut a retourner. Defaut = ['*']
     * @return stdclass retourne un stdClass vide si aucun resultat ne correspond a $filter
     */
    public function get_entry(string $filter, array $returnedAttrs = ['*']): \stdclass
    {
        $limit = $this->search_options->get_result_limit();
        $this->search_options->set_result_limit(1);
        $res = $this->search($filter, $returnedAttrs);
        $this->search_options->set_result_limit($limit);
        return count($res) > 0 ? $res[0] : new \stdClass();
    }

    /**
     * Modify a LDAP entry
     * @param string $entry_dn The distinguished name of the entry
     * @param array | LdapBatchModification $target_entry_attr The attributes to modify and there values or a LdapBatchModification object
     * @param int $modify_type can be one of the constant MOD_ADD,MOD_DEL,MOD_REPLACE of ConnectionLDAP
     * @return bool Return True on success.
     * @throws Exception throw an exception on fail.
     */
    public function modify(string $entry_dn, $target_entry_attr, int $modify_type): bool
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
            throw new Exception("Can't performe modification of $entry_dn cause : " . $this->getLastError());
        }
        return $result;
    }

    /**
     * Create an LDAP entry
     * @param string $entry_dn The distinguished name of the entry
     * @param array $ldap_entry_attr the attributes of the entry
     * @return bool Return True on success.
     * @throws Exception throw an exception on fail.
     */
    public function add(string $entry_dn, array $ldap_entry_attr): bool
    {
        if (!@ldap_add($this->connection, $entry_dn, $ldap_entry_attr)) {
            throw new Exception("Can't add ldap entry $entry_dn cause : " . $this->getLastError());
        }
        return true;
    }

    /**
     * Delete the ldap entry specified by $entry_dn
     * @param string $entry_dn The distinguished name of the entry
     * @return bool Return True on success.
     * @throws Exception throw an exception on fail.
     */
    public function delete(string $entry_dn): bool
    {
        if (!@ldap_delete($this->connection, $entry_dn)) {
            throw new Exception("Can't delete ldap entry $entry_dn cause : " . $this->getLastError());
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
    public function get_search_options(): LdapSearchOptions
    {
        return $this->search_options;
    }
}
