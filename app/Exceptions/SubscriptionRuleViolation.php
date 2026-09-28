<?php

namespace App\Exceptions;

use RuntimeException;

class SubscriptionRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
