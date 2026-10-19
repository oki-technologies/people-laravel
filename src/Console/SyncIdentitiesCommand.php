<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Console;

use Illuminate\Console\Command;
use Oki\People\Laravel\Contracts\HandlesIdentityEvents;
use Oki\People\People;

/**
 * Applies what happened to the people who signed in here, until up to date.
 *
 * Schedule it every minute; each run resumes where the last one stopped.
 */
final class SyncIdentitiesCommand extends Command
{
    protected $signature = 'people:sync {--limit=100 : Events per page}';

    protected $description = 'Apply People\'s identity events to this application';

    public function handle(People $people, HandlesIdentityEvents $handler): int
    {
        $applied = 0;
        $limit = max(1, (int) $this->option('limit'));

        do {
            $page = $people->identityEvents()->after($handler->cursor(), $limit);

            foreach ($page->events as $event) {
                $handler->handle($event);
                $handler->advance($event->sequence);
                $applied++;
            }
        } while ($page->next !== null && count($page->events) === $limit);

        $this->components->info("{$applied} identity events applied.");

        return self::SUCCESS;
    }
}
