## Patch Note

### 1.1.1 (Breaking changes)

- Rename `ConnectionLDAP::fromFile` to `ConnectionLDAP::from_file` fit naming convention.

## Install

> composer require hakuryo/ldap-client:^1

## Dependencies

### Mandatory

- PHP >= 7.x 

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
$ldap->get_entry($ldap_filer,$attr_list);

// Modify serach_options
$ldap->get_search_options()->set_base_dn("ou=my_ou,dc=exemple,dc=com");
$ldap->get_search_options()->set_result_limit(1);
$ldap->get_search_options()->set_scope(LdapSearchOptions::SEARCH_SCOPE_ONE_LEVEL);

// You can chain modification
$ldap->get_search_options()->set_result_limit(1)->set_scope(LdapSearchOptions::SEARCH_SCOPE_ONE_LEVEL);

```
