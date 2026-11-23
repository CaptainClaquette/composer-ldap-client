# hakuryo/ldap-client

A lightweight PHP LDAP client providing connection helpers, LDAP searches, pagination, entry management, and optional object hydration with PHP 8 attributes.

## Requirements

- PHP **8.1 or newer** (as declared in `composer.json`)
- PHP `ldap` extension
- Composer

The library requires `hakuryo/config-parser:^1.1`.

## Installation

```bash
composer require hakuryo/ldap-client
```

## Connecting

```php
use hakuryo\ldap\ConnectionLDAP;

$ldap = new ConnectionLDAP(
    'ldap://ldap.example.org',
    'cn=admin,dc=example,dc=org',
    'secret',
    5
);
```

The constructor accepts an optional fifth argument of type `LdapSearchOptions`. Without one, the library attempts to determine the root DN from the bound identity.

### Configuration file

`config.ini`:

```ini
[ldap]
HOST="ldap://ldap.example.org"
USER="cn=admin,dc=example,dc=org"
PWD="secret"
DN="dc=example,dc=org"
TIMEOUT=5
```

```php
$ldap = ConnectionLDAP::fromFile('config.ini', 'ldap');
```

`HOST`, `USER`, `PWD`, and `DN` are required by the file-based configuration. `TIMEOUT` defaults to 5 seconds. Keep credentials outside version control.

## Search options

```php
use hakuryo\ldap\entities\LdapSearchOptions;

$options = new LdapSearchOptions(
    'ou=people,dc=example,dc=org',
    0,
    LdapSearchOptions::SEARCH_SCOPE_SUB
);

$ldap = new ConnectionLDAP(
    'ldap://ldap.example.org',
    'cn=admin,dc=example,dc=org',
    'secret',
    5,
    $options
);

$options->setBaseDN('ou=staff,dc=example,dc=org');
$options->setResultLimit(100);
$options->setScope(LdapSearchOptions::SEARCH_SCOPE_ONE_LEVEL);
```

`SEARCH_SCOPE_SUB` and `SEARCH_SCOPE_ONE_LEVEL` are used by `getEntry()`. The `search()` method always searches the subtree; `list()` always searches one level.

## Searching entries

### `search()` — subtree search

```php
$users = $ldap->search(
    '(objectClass=person)',
    ['uid', 'cn', 'mail']
);

foreach ($users ?? [] as $user) {
    echo $user->uid ?? '';
}
```

### `list()` — one-level search

```php
$users = $ldap->list('(objectClass=person)', 'uid,cn,mail');
```

### `getEntry()` — first matching entry

```php
$user = $ldap->getEntry('(uid=jdoe)', ['uid', 'cn', 'mail']);

if ($user !== null) {
    echo $user->dn;
}
```

By default, entries are returned as `stdClass` objects. The `dn` property is included. LDAP attributes with one value are represented as scalars; attributes with multiple values are arrays. `search()` and `list()` return `null` when no entries match; `getEntry()` returns `null` when no entry matches.

### Pagination

```php
$users = $ldap->search(
    '(objectClass=person)',
    ['uid', 'cn'],
    pageSize: 250
);
```

The server must support LDAP paged-results controls. Pagination is also supported by `list()`.

### `trackBy`

Use an LDAP attribute as the associative-array key:

```php
$users = $ldap->search(
    '(objectClass=person)',
    ['uid', 'cn'],
    trackBy: 'uid'
);

$user = $users['jdoe'] ?? null;
```

The tracking key is read from the normalized LDAP entry **before** object hydration, and is case-insensitive. It must be present and must be a string or integer. **Duplicate keys overwrite earlier results**; this behavior is intentional. When using `trackBy`, include the tracking attribute in `returnedAttrs`.

### Callbacks

A callback is invoked once per returned entry, after optional hydration. It may modify the object in place:

```php
$users = $ldap->search(
    '(objectClass=person)',
    ['uid', 'cn'],
    callback: function (object $entry): void {
        $entry->cn = strtoupper($entry->cn);
    }
);
```

The callback return value is ignored. Callbacks are also available on `list()` and `getEntry()`.

## Object hydration

Pass `className` to `search()`, `list()`, or `getEntry()` to obtain instances of a custom class instead of `stdClass`.

The hydrator uses **hybrid mapping**: it matches property names to LDAP attribute names by default, or uses an explicit `#[LdapAttribute]` mapping when supplied. Matching is case-insensitive.

> **Important:** In the supplied project snapshot, `ObjectHydrator.php` imports `hakuryo\ldap\attributes\LdapAttribute`, while the actual attribute is declared in `hakuryo\ldap\Attributes`. Correct the import to `use hakuryo\ldap\Attributes\LdapAttribute;` before relying on explicit mappings with Composer PSR-4 autoloading.

```php
use hakuryo\ldap\Attributes\LdapAttribute;

class User
{
    private string $uid;

    #[LdapAttribute('cn')]
    private string $fullName;

    private array $mail = [];

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getMail(): array
    {
        return $this->mail;
    }
}

$users = $ldap->search(
    '(objectClass=person)',
    ['uid', 'cn', 'mail'],
    className: User::class
);

foreach ($users ?? [] as $user) {
    echo $user->getFullName();
}
```

### Hydration rules

- Public, protected, and private properties are supported via reflection.
- Static and `readonly` properties are skipped.
- Constructors are **not called** (`newInstanceWithoutConstructor()`).
- Properties without a matching LDAP attribute retain their default value, or remain uninitialized if they have no default.
- When a property is typed `array`, a single LDAP value is wrapped in an array.
- Other type mismatches are left to PHP to handle.
- The hydrator does not perform arbitrary scalar conversions or handle union types specially.
- LDAP attributes without matching properties are ignored.

```php
$user = $ldap->getEntry(
    '(uid=jdoe)',
    ['uid', 'cn', 'mail'],
    className: User::class
);
```

## Modifying LDAP entries

### Add

```php
$ldap->add('uid=jdoe,ou=people,dc=example,dc=org', [
    'objectClass' => ['top', 'person', 'organizationalPerson', 'inetOrgPerson'],
    'uid' => 'jdoe',
    'cn' => 'Jane Doe',
    'sn' => 'Doe',
]);
```

### Modify

```php
use hakuryo\ldap\ConnectionLDAP;

$dn = 'uid=jdoe,ou=people,dc=example,dc=org';

$ldap->modify($dn, ['mail' => 'jane.doe@example.org']);
$ldap->modify($dn, ['description' => 'Staff'], ConnectionLDAP::MOD_ADD);
$ldap->modify($dn, ['description' => 'Staff'], ConnectionLDAP::MOD_DEL);
```

The default modification mode is `MOD_REPLACE`.

### Rename or move

```php
$ldap->rename(
    'uid=jdoe,ou=people,dc=example,dc=org',
    'uid=jane.doe',
    'ou=staff,dc=example,dc=org'
);
```

### Delete

```php
$ldap->delete('uid=jane.doe,ou=staff,dc=example,dc=org');
```

### Disconnect

```php
$ldap->disconnect();
```

## Exceptions

LDAP operations use dedicated exception classes under `hakuryo\ldap\entities\exceptions`:

- `LDAPConnectException` and `LDAPBindException`
- `LDAPSearchException`
- `LDAPAddException`
- `LDAPModifyException`
- `LDAPDeleteException`

For example:

```php
use hakuryo\ldap\entities\exceptions\LDAPSearchException;

try {
    $users = $ldap->search('(objectClass=person)');
} catch (LDAPSearchException $e) {
    error_log($e->getMessage());
}
```

Configuration parsing may also throw exceptions from `hakuryo/config-parser`.

## Active Directory deprecation

Active Directory-specific helpers (`ADToggleAccountActivation()` and `ADSetPassword()`) are marked `@deprecated` and are planned for removal in a future release. They are not recommended for new integrations. No removal version has been announced.

## License

MIT.
