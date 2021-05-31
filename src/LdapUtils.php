<?php

namespace hakuryo\ldap;

use Exception;

trait LdapUtils {

    public function clear_ldap_result($entrys): array {
        $res = array();
        unset($entrys["count"]);
        foreach ($entrys as $line) {
            $temp = new \stdClass();
            $temp->dn = $line["dn"];
            unset($line["count"]);
            unset($line["dn"]);
            foreach ($line as $key => $value) {
                if (is_string($key)) {
                    unset($value["count"]);
                    $temp->$key = count($value) > 1 ? $value : utf8_encode($value[0]);
                }
            }
            array_push($res, $temp);
        }
        return $res;
    }

    public function create_groupOfNames(string $name, string $target_ou, array $members) {
        $attrs = [];
        $attrs['objectclass'] = "groupOfNames";
        $attrs['cn'] = $name;
        $attrs['member'] = $members;
        $this->add("cn=$name,$target_ou", $attrs);
    }

    public function format_password($pass) {
        return "{SHA}" . base64_encode(pack("H*", sha1($pass)));
    }

    public function get_root_dn() {
        $mydn = ldap_exop_whoami($this->connection);
        $matches = array();
        preg_match('/dc=.*/', $mydn, $matches);
        if (count($matches) > 0) {
            return $matches[0];
        } else {
            throw new Exception("Can't retrieve root_dn from provided user dn");
        }
    }

    public function getLastError() {
        $msg = "";
        ldap_get_option($this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $msg);
        return "[ERROR_CODE]" . ldap_errno($this->connection) . " " . ldap_error($this->connection) . " " . "$msg";
    }

}
