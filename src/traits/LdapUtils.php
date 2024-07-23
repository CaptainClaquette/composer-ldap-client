<?php

namespace hakuryo\ldap;

use Exception;
use hakuryo\ldap\exceptions\LDAPSearchException;

trait LdapUtils
{

    public static function clearEntry(ConnectionLDAP $connection, $entry): \stdClass
    {
        $temp = new $connection->entryClass();
        $temp->dn = ldap_get_dn($connection->connection, $entry);
        $attributs = ldap_get_attributes($connection->connection, $entry);
        unset($attributs["count"]);
        foreach ($attributs as $key => $value) {
            if (is_string($key)) {
                unset($value["count"]);
                $temp->$key = count($value) > 1 ? $value : $value[0];
            }
        }
        return $temp;
    }


    public static function processResults(ConnectionLDAP $connection, $results, &$entrys, $class = \stdClass::class): void
    {
        if (!$results) {
            throw new LDAPSearchException("Can't perform research cause : " . $connection->getLastError());
        }
        $entryId = ldap_first_entry($connection->connection, $results);
        if ($entryId !== false) {
            $entry = self::clearEntry($connection, $entryId, $class);
            $entrys[] = $entry;
            while ($entryId = ldap_next_entry($connection->connection, $entryId)) {
                $entry = self::clearEntry($connection, $entryId, $class);
                $entrys[] = $entry;
            }
        }
    }

    public
    function formatPassword($pass)
    {
        return "{SHA}" . base64_encode(pack("H*", sha1($pass)));
    }

    public
    function getRootDN()
    {
        $mydn = ldap_exop_whoami($this->connection);
        $matches = array();
        preg_match('/dc=.*/', $mydn, $matches);
        if (count($matches) > 0) {
            return $matches[0];
        } else {
            throw new Exception("Can't retrieve root_dn from provided user dn");
        }
    }

    public
    function getLastError()
    {
        $msg = "";
        ldap_get_option($this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $msg);
        return "[ERROR_CODE]" . ldap_errno($this->connection) . " " . ldap_error($this->connection) . " " . "$msg";
    }

}
