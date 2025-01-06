<?php

namespace hakuryo\ldap\traits;

use Exception;
use hakuryo\ldap\ConnectionLDAP;
use hakuryo\ldap\entities\exceptions\LDAPSearchException;

trait LdapUtils
{

    public static function clearEntry(ConnectionLDAP $connection, $entry, ?callable $callback): \stdClass
    {
        $temp = new \stdClass();
        $temp->dn = ldap_get_dn($connection->connection, $entry);
        $attributs = ldap_get_attributes($connection->connection, $entry);
        unset($attributs["count"]);
        foreach ($attributs as $key => $value) {
            if (is_string($key)) {
                unset($value["count"]);
                $temp->$key = count($value) > 1 ? $value : $value[0];
            }
        }
        if ($callback != null) {
            call_user_func($callback, $temp);
        }
        return $temp;
    }


    public static function processResults(ConnectionLDAP $connection, $results, &$entries, ?callable $callback, ?string $trackBy): void
    {
        if (!$results) {
            throw new LDAPSearchException("Can't perform research cause : " . $connection->getLastError());
        }
        $entryId = null;
        do {
            if ($entryId === null) {
                $entryId = ldap_first_entry($connection->connection, $results);
            } else {
                $entryId = ldap_next_entry($connection->connection, $entryId);
            }
            if ($entryId !== false) {
                $entry = self::clearEntry($connection, $entryId, $callback);
                if ($trackBy != null) {
                    $entries[$entry->$trackBy] = $entry;
                } else {
                    $entries[] = $entry;
                }
            }
        } while ($entryId);
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
