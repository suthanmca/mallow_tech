<?php

namespace App\Data;

use App\Models\UsageEvent;

final readonly class UsageEventResult
{
    public function __construct(
        public UsageEvent $event,
        public bool $created,
    ) {}
}
