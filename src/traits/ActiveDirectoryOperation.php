<?php

namespace hakuryo\ldap\traits;

use Exception;
use hakuryo\ldap\ConnectionLDAP;

trait ActiveDirectoryOperation
{

    public function ad_toggle_account_activation(string $uid, bool $active): bool
    {
        $res = $this->get_entry("cn=" . $uid, array("distinguishedname"));
        if (property_exists($res, 'distinguishedname')) {
            $entry = [];
            $entry["userAccountControl"] = $active ? intval(ConnectionLDAP::USER_ACCOUNT_ENABLE + ConnectionLDAP::USER_NORMAL_ACCOUNT + ConnectionLDAP::USER_DONT_EXPIRE_PASSWD, 16) : intval(ConnectionLDAP::USER_ACCOUNT_DISABLE + ConnectionLDAP::USER_NORMAL_ACCOUNT + ConnectionLDAP::USER_DONT_EXPIRE_PASSWD, 16);
            if (ldap_mod_replace($this->connection, $res->distinguishedname, $entry)) {
                return true;
            } else {
                throw new Exception("[LDAPActiveDirectoryTools::toggle_account_activation] fail to search user dn cause : " . $this->getLastError());
            }
        } else {
            throw new Exception("[LDAPActiveDirectoryTools::toggle_account_activation] no user $uid found");
        }
    }

    public function ad_set_password(string $cn, string $mdp): bool
    {
        if (!($search = ldap_search($this->connection, $this->get_search_options()->get_base_dn(), "cn=$cn"))) {
            throw new Exception("[LDAPActiveDirectoryTools::set_password] fail to search user dn cause : " . $this->getLastError());
        }
        //recherche ldap
        $info = ldap_get_entries($this->connection, $search);
        if ($info) {
            // recuperation de la premiere entre ldap correspondant
            $entry = ldap_first_entry($this->connection, $search);
            // recuperation du dn de l'entre
            $dnareset = ldap_get_dn($this->connection, $entry);
            //formatage du mot de passe pour AD
            $mdpreset = "\"" . $mdp . "\"";
            $userdata["unicodepwd"] = mb_convert_encoding($mdpreset, "UTF-16LE");
            //affectation du nouveau mot de passe à l'entre
            if (!ldap_mod_replace($this->connection, $dnareset, $userdata)) {
                throw new Exception("[LDAPActiveDirectoryTools::set_password] fail set password for user $cn, cause : " . $this->getLastError());
            }
            return true;
        }
    }

/*     public function ad_add_user($uid, $entry, $subOu, $domain)
    {
        $res = $this->search("uid=" . $uid, array("uid"));
        if (count($res) > 0) {
            return false;
        } else {
            $entry['objectclass'] = array("top", "person", "organizationalPerson", "user");
            $entry['sAMAccountName'] = $entry["uid"];
            $entry['cn'] = $entry["uid"];
            $entry['userPrincipalName'] = $entry["uid"] . "@$domain";
            $entry['userAccountControl'] = intval(ConnectionLDAP::USER_ACCOUNT_DISABLE + ConnectionLDAP::USER_NORMAL_ACCOUNT + ConnectionLDAP::USER_DONT_EXPIRE_PASSWD, 16);
            try {
                error_log($this->ad_get_user_dn($uid, $subOu));
                return ldap_add($this->connection, $this->ad_get_user_dn($uid, $subOu), $entry);
            } catch (Exception $e) {
                var_dump(ldap_errno($this->connection));
                return false;
            }
        }
    } */

    /**
     * Get the distinguishedName of a user 
     * 
     * @param ConnectionLDAP $this a valide ConnectionLDAP
     * @param string $cn the user URCA login
     * @return string the dn of the user
     * @throws Exception if any problème occure in the process
     */
    public function ad_get_user_dn(string $cn): string
    {
        $dn = '';
        if (!($search = ldap_search($this->connection, $this->get_search_options()->get_base_dn(), "cn=$cn"))) {
            throw new Exception("[LDAPActiveDirectoryTools::get_user_dn] fail to search user cause : " . $this->getLastError());
        }
        //recherche ldap
        $info = ldap_get_entries($this->connection, $search);
        if ($info) {
            // recuperation de la premiere entre ldap correspondant
            $entry = ldap_first_entry($this->connection, $search);
            if (!$entry) {
                throw new Exception("[LDAPActiveDirectoryTools::get_user_dn] user with cn: $cn don't exist");
            }
            $dn = ldap_get_dn($this->connection, $entry);
            if (!$dn) {
                throw new Exception("[LDAPActiveDirectoryTools::get_user_dn] fail to get entry DN cause : " . $this->getLastError());
            }
        } else {
            throw new Exception("[LDAPActiveDirectoryTools::get_user_dn] fail to get entries cause : " . $this->getLastError());
        }
        return $dn;
    }

    /*     public function move_user(ConnectionLDAP $this, string $cn, string $targetOuDn): bool {
        $dn = $this->get_user_dn($this, $cn);
        if ($dn != null && $dn != '') {
            if (!ldap_rename($this->connection, $dn, "CN=" . $uid, $targetOuDn, true)) {
                throw new Exception("[LDAPActiveDirectoryTools::move_user] fail to move user with cn : $cn in OU : $targetOuDn cause : ". $this->getLastError());
            }
        } else {
            throw new Exception("[LDAPActiveDirectoryTools::move_user]  DN of user with cn : $cn is empty");
        }
        return true;
    } */
}
