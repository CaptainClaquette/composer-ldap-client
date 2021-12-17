<?php

namespace hakuryo\ldap;

class LdapBatchModification
{
    const ADD = LDAP_MODIFY_BATCH_ADD;
    const REPACE = LDAP_MODIFY_BATCH_REPLACE;
    const REMOVE = LDAP_MODIFY_BATCH_REMOVE;
    const DELETE = LDAP_MODIFY_BATCH_REMOVE_ALL;

    private $attrs = [];

    public function __construct(array $batchAttrs)
    {
        $this->attrs = $batchAttrs;
    }

    public function add(string $attr_name, int $modification_type, array $values = [])
    {
        if ($modification_type !== self::DELETE) {
            array_push($this->attrs, ["attrib" => $attr_name, "modtype" => $modification_type, "values" => $values]);
        } else {
            array_push($this->attrs, ["attrib" => $attr_name, "modtype" => $modification_type]);
        }
        return $this;
    }

    public function get_attrs()
    {
        return $this->attrs;
    }
}
