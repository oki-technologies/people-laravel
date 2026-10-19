<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Contracts;

use Oki\People\Events\IdentityEvent;

/**
 * What `people:sync` hands the identity events feed to.
 *
 * Keep the cursor somewhere durable — a database row, not the cache — and
 * make `handle()` safe to repeat: an event can arrive twice.
 */
interface HandlesIdentityEvents
{
    /** The last sequence applied, or 0 to start from the beginning. */
    public function cursor(): int;

    public function handle(IdentityEvent $event): void;

    /** Every event up to and including this sequence has been applied. */
    public function advance(int $sequence): void;
}
