<?php

namespace hakuryo\ldap\traits;

use hakuryo\ldap\ConnectionLDAP;
use hakuryo\ldap\entities\exceptions\LDAPModifyException;
use hakuryo\ldap\entities\exceptions\LDAPSearchException;

trait ActiveDirectoryOperation
{


    public function ADToggleAccountActivation(string $uid, bool $active): bool
    {
        $res = $this->getEntry("cn=$uid", ["dn"]);
        if ($res !== null) {
            $entry = [];
            $entry["userAccountControl"] = $active ? intval(ConnectionLDAP::USER_ACCOUNT_ENABLE + ConnectionLDAP::USER_NORMAL_ACCOUNT + ConnectionLDAP::USER_DONT_EXPIRE_PASSWD, 16) : intval(ConnectionLDAP::USER_ACCOUNT_DISABLE + ConnectionLDAP::USER_NORMAL_ACCOUNT + ConnectionLDAP::USER_DONT_EXPIRE_PASSWD, 16);
            if (ldap_mod_replace($this->connection, $res->dn, $entry)) {
                return true;
            } else {
                throw new LDAPModifyException("[LDAPActiveDirectoryTools::toggle_account_activation] fail to search user dn cause : " . $this->getLastError());
            }
        } else {
            throw new LDAPSearchException("[LDAPActiveDirectoryTools::toggle_account_activation] no user $uid found");
        }
    }

    public function ADSetPassword(string $cn, string $mdp): bool
    {
        if (!($search = ldap_search($this->connection, $this->getSearchOptions()->getBaseDN(), "cn=$cn"))) {
            throw new LDAPSearchException("[LDAPActiveDirectoryTools::set_password] fail to search user dn cause : " . $this->getLastError());
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
                throw new LDAPModifyException("[LDAPActiveDirectoryTools::set_password] fail set password for user $cn, cause : " . $this->getLastError());
            }
            return true;
        }else{
            throw new LDAPSearchException("[LDAPActiveDirectoryTools::set_password] Can't retrieve entry cause : " . $this->getLastError());
        }
    }
}
