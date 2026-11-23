<?php

namespace hakuryo\ldap\entities;

use hakuryo\ldap\Attributes\LdapAttribute;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use stdClass;

final class ObjectHydrator
{
    public static function hydrate(stdClass $entry, string $className): object
    {
        $reflection = new ReflectionClass($className);
        $object = $reflection->newInstanceWithoutConstructor();
        $attributes = array_change_key_case(get_object_vars($entry), CASE_LOWER);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->isReadOnly()) {
                continue;
            }

            $ldapName = self::getLdapAttributeName($property);
            if (!array_key_exists($ldapName, $attributes)) {
                continue;
            }

            $value = self::normalizeValue(
                $attributes[$ldapName],
                $property
            );
            $property->setValue($object, $value);
        }
        return $object;
    }

    private static function getLdapAttributeName(ReflectionProperty $property): string
    {
        $attributes = $property->getAttributes(LdapAttribute::class);

        if (!empty($attributes)) {
            return strtolower($attributes[0]->newInstance()->name);
        }

        return strtolower($property->getName());
    }

    private static function normalizeValue(mixed $value, ReflectionProperty $property): mixed
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType
            && $type->getName() === 'array'
            && !is_array($value)
        ) {
            return [$value];
        }

        return $value;
    }
}