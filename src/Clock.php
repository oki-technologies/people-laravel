<?php

declare(strict_types=1);

namespace Oki\People\Laravel;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** Now, as the application reckons it — so a test that travels in time moves the SDK too. */
final class Clock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }
}
