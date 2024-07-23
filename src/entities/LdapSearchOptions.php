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

    private $baseDN;
    private $resultLimit;
    private $scope;

    
    public function __construct(string $base_dn, int $result_limit = 0, string $scope = self::SEARCH_SCOPE_SUB) {
        $this->baseDN = $base_dn;
        $this->resultLimit = $result_limit;
        $this->scope = $scope;
    }

    public function setBaseDN(string $dn): LdapSearchOptions {
        $this->baseDN = $dn;
        return $this;
    }

    public function setResultLimit(int $limit): LdapSearchOptions {
        $this->resultLimit = $limit;
        return $this;
    }

    public function setScope(int $scope): LdapSearchOptions {
        $this->scope = $scope;
        return $this;
    }

    public function getBaseDN(): string {
        return $this->baseDN;
    }

    public function getResultLimit(): int {
        return $this->resultLimit;
    }

    public function getScope(): int {
        return $this->scope;
    }

}
