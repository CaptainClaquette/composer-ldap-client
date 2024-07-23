## Patch Note

### 2.0.0 (breaking change)

- cleaned unused code
- refactoring project, classes moved in new directory / namespace 
- Naming convention is now CamelCase
- getEntry now return null if no result instead of stdclass
- Ldap operation now throw specifique errors
  - search / list / getEntry => LDAPSearchException
  - add => LDAPAddException
  - modify => LDAPModifyException
  - delete => LDAPDeleteException
  - fromFile => LDAPConnectException / LDAPBinDException

#### new function

- list perform a single level search to get more performance.

#### modified function

- search now perform a subtree search event if scope is one_level.
- getEntry perform a search taking account of scope

### 1.1.1 (Breaking changes)

- Rename `ConnectionLDAP::fromFile` to `ConnectionLDAP::from_file` fit naming convention.

## Install

> composer require hakuryo/ldap-client:^1

## Dependencies

### Mandatory

- PHP >= 8.1 

## Features
- Parsing client config from INI and JSON file

## Usage & exemples

### Exemple INI file
```INI
[ldap]
HOST="ldap://myldap.mydomain.com"
;LDAPS
;HOST="ldaps://myldap.mydomain.com"
USER="cn=admin,dc=mydomain, dc=com"
DN="dc=mydomain, dc=com"
PWD="my_super_secure_password"
;OPTIONAL
;network_timeout in second
TIMEOUT=5
```

### ConnectionLDAP usage

```PHP
require_once './vendor/autoload.php';

use hakuryo\ldap\ConnectionLDAP;

// Basic connection 
$ldap = new ConnectionLDAP("myldap.mydomain.com","uid=user,ou=people,dc=mydomain,dc=com")

// From File
$ldap = ConnectionLDAP::from_file("path_to_my_ldap_ini_file");

// You can specify a section of your ini file
$ldap = ConnectionLDAP::from_file("path_to_my_ldap_ini_file","ldap_section");

//ldap_search
$ldap_filter = "memberof=cn=admin,ou=groups,dc=mydomain,dc=com";
$attr_list = ["uid","displayname","sn","givenname"];
$results = $ldap->search($ldap_filer,$attr_list);
foreach($result as $entry){
    echo json_encode($entry,JSON_PRETTY_PRINT);
}

// get an specifique entry
$ldap->getEntry($ldap_filer,$attr_list);

// Modify serach_options
$ldap->getSearchOptions()->setBaseDN("ou=my_ou,dc=exemple,dc=com");
$ldap->getSearchOptions()->setResultLimit(1);
$ldap->getSearchOptions()->setScope(LdapSearchOptions::SEARCH_SCOPE_ONE_LEVEL);

// You can chain modification
$ldap->getSearchOptions()->setResultLimit(1)->setScope(LdapSearchOptions::SEARCH_SCOPE_ONE_LEVEL);

```
