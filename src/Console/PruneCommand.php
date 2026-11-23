<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Console;

use Illuminate\Console\Command;
use Stifter\SiteAnalytics\ClickRecorder;
use Stifter\SiteAnalytics\EventRecorder;

/**
 * Deletes events and clicks older than their configured retention. Schedule it daily and set prune_lottery to [0, 1] to stop pruning during requests.
 */
final class PruneCommand extends Command
{
    protected $signature = 'site-analytics:prune';

    protected $description = 'Delete analytics events and clicks older than their configured retention';

    public function handle(EventRecorder $events, ClickRecorder $clicks): int
    {
        $deletedEvents = $events->pruneExpiredEvents();
        $deletedClicks = $clicks->pruneExpired();

        $this->components->info(sprintf(
            'Deleted %d expired %s and %d expired %s.',
            $deletedEvents,
            $deletedEvents === 1 ? 'event' : 'events',
            $deletedClicks,
            $deletedClicks === 1 ? 'click' : 'clicks',
        ));

        return self::SUCCESS;
    }
}
