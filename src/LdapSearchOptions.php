<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace hakuryo\ldap;

/**
 * Description of LdapSearchOptions
 *
 * @author Hakuryo
 */
class LdapSearchOptions {

    const SEARCH_SCOPE_SUB = 0;
    const SEARCH_SCOPE_ONE_LEVEL = 1;

    private $base_dn;
    private $result_limit;
    private $scope;

    
    public function __construct(string $base_dn, int $result_limit = 0, string $scope = self::SEARCH_SCOPE_SUB) {
        $this->base_dn = $base_dn;
        $this->result_limit = $result_limit;
        $this->scope = $scope;
    }

    public function set_base_dn(string $dn): LdapSearchOptions {
        $this->base_dn = $dn;
        return $this;
    }

    public function set_result_limit(int $limit): LdapSearchOptions {
        $this->result_limit = $limit;
        return $this;
    }

    public function set_scope(int $scope): LdapSearchOptions {
        $this->scope = $scope;
        return $this;
    }

    public function get_base_dn(): string {
        return $this->base_dn;
    }

    public function get_result_limit(): int {
        return $this->result_limit;
    }

    public function get_scope(): int {
        return $this->scope;
    }

}
