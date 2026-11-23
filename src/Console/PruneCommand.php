<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Console;

use Illuminate\Console\Command;
use Stifter\SiteAnalytics\EventRecorder;

/**
 * Deletes events older than their configured retention. Schedule it daily and set prune_lottery to [0, 1] to stop pruning during requests.
 */
final class PruneCommand extends Command
{
    protected $signature = 'site-analytics:prune';

    protected $description = 'Delete analytics events older than their configured retention';

    public function handle(EventRecorder $recorder): int
    {
        $deleted = $recorder->pruneExpiredEvents();

        $this->components->info(sprintf('Deleted %d expired %s.', $deleted, $deleted === 1 ? 'event' : 'events'));

        return self::SUCCESS;
    }
}
