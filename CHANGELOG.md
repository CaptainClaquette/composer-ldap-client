# Changelog

Notable changes to `hakuryo/ldap-client` are documented here.

## 3.0.0

### Added

- Optional custom-class hydration for `search()`, `list()`, and `getEntry()` using the `className` parameter.
- `ObjectHydrator` with automatic case-insensitive property-to-LDAP attribute mapping.
- `#[LdapAttribute('attributeName')]` for explicit mappings when PHP property names differ from LDAP attribute names.
- Support for hydrating public, protected, and private properties.
- Automatic wrapping of single LDAP values into arrays when the destination property has the `array` type.

### Changed

- Callbacks are executed after optional object hydration in `processResults()` and `getEntry()`.
- `trackBy` keys are extracted from normalized LDAP data before hydration, independently of the target class.
- Static and `readonly` properties are skipped by the hydrator.
- Search options scope parameter uses an integer type.
- Pagination propagates the optional hydration class through paged searches.

### Deprecated

- Active Directory-specific methods `ADToggleAccountActivation()` and `ADSetPassword()` are marked `@deprecated` and planned for removal in a future release.

### Notes

- Existing default `stdClass` results remain supported when no `className` is specified.
- Duplicate `trackBy` keys intentionally overwrite earlier entries.
- Constructors are not executed during hydration; incompatible property types are handled by PHP.
- **Before release:** align the `LdapAttribute` import namespace in `ObjectHydrator.php` with the actual `hakuryo\ldap\Attributes` namespace. The uploaded snapshot still contains a case mismatch.

## 2.2.0

### Added

- `trackBy` parameter for `search()` and `list()` to key results by an LDAP attribute.
- Callback parameter for `search()`, `list()`, and the single-entry retrieval method.

### Changed

- `returnedAttrs` accepts a comma-separated attribute list.
- Returned attribute names retain their LDAP schema casing rather than being forced to lowercase.
- Switched autoloading to PSR-4 and adjusted exception namespaces.
- Replaced the bundled configuration parser with `hakuryo/config-parser`.

### Fixed

- Pagination handling for `list()`.

## 2.1.0

### Added

- Pagination parameter for `search()`.

## 2.0.0

### Changed

- Refactored the project layout and namespaces (breaking changes).
- Adopted camelCase naming conventions.
- `getEntry()` returns `null` when no entry matches.
- LDAP operations use dedicated exception types.

### Added

- One-level `list()` search.
- Scope-aware `getEntry()` search.

## 1.1.1

### Changed

- Historical breaking rename of `ConnectionLDAP::fromFile` to `ConnectionLDAP::from_file`, as recorded in the original changelog. The current API uses `fromFile()`.
