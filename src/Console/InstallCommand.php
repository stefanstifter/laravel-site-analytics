<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Console;

use Illuminate\Console\Command;

/**
 * Publishes the config, offers to run the migrations and download the country database, and lists what is left to wire up.
 */
final class InstallCommand extends Command
{
    protected $signature = 'site-analytics:install';

    protected $description = 'Publish the site analytics config, run its migrations and download the country database';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'site-analytics-config']);

        if ($this->confirm('Run the migrations now?', true)) {
            $this->call('migrate');
        }

        if ($this->confirm('Download the country database now? It is about 8 MB, from DB-IP.', true)) {
            $this->call('site-analytics:geoip-update');
        }

        $this->components->info('Site analytics is installed. Next:');
        $this->components->bulletList([
            "Record page views: set 'track_group' => 'web' in config/site-analytics.php, or add ->middleware('site-analytics') to your public routes.",
            "Keep admin and login pages out with 'exclude'.",
            "Decide who sees the reports: Gate::define('viewSiteAnalytics', fn (\$user) => ...). By default any logged-in user can.",
            'Keep the country database fresh: run site-analytics:geoip-update monthly, or set geoip.auto_update to true.',
            'Open the reports at /'.trim((string) config('site-analytics.routes.prefix'), '/').'. The System tab shows whether everything works.',
        ]);

        return self::SUCCESS;
    }
}
