<?php

namespace hakuryo\ldap\traits;

use Exception;
use hakuryo\ldap\ConnectionLDAP;
use hakuryo\ldap\entities\exceptions\LDAPSearchException;
use hakuryo\ldap\entities\ObjectHydrator;

trait LdapUtils
{

    public static function clearEntry(ConnectionLDAP $connection, $entry): \stdClass
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
        return $temp;
    }


    public static function processResults(ConnectionLDAP $connection, $results, &$entries, ?callable $callback, ?string $trackBy, ?string $className = null): void
    {
        if (!$results) {
            throw new LDAPSearchException("Can't perform research cause : " . $connection->getLastError());
        }
        $entryId = ldap_first_entry($connection->connection, $results);

        while ($entryId !== false) {
            // Normalize LDAP entry
            $entry = self::clearEntry($connection, $entryId);
            // Retrieve tracking key
            $key = null;
            if ($trackBy !== null) {
                $attributes = array_change_key_case(get_object_vars($entry), CASE_LOWER);
                $attributeName = strtolower($trackBy);

                if (!array_key_exists($attributeName, $attributes)) {
                    throw new LDAPSearchException(
                        "Tracking attribute '$trackBy' not found"
                    );
                }

                $key = $attributes[$attributeName];
                if (!is_string($key) && !is_int($key)) {
                    throw new LDAPSearchException(
                        "Tracking attribute '$trackBy' must be scalar"
                    );
                }
            }

            // Hydrate entry if a class is provided
            $result = $className !== null ? ObjectHydrator::hydrate($entry, $className) : $entry;

            if ($callback !== null) {
                call_user_func($callback, $result);
            }

            if ($trackBy !== null) {
                $entries[$key] = $result;
            } else {
                $entries[] = $result;
            }
            // get next entry
            $entryId = ldap_next_entry($connection->connection, $entryId);
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
