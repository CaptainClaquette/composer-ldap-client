<?php

namespace hakuryo\ldap\exceptions;

class LDAPDeleteException extends \Exception
{
 public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
 {
     parent::__construct($message, $code, $previous);
 }
}
