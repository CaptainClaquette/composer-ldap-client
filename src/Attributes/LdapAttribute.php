<?php

namespace hakuryo\ldap\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class LdapAttribute
{
    public function __construct(
        public readonly string $name
    )
    {
    }
}