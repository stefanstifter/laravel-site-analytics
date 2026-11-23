<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Console;

use Illuminate\Console\Command;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\GeoIp\CountryDatabaseUpdateFailed;
use Stifter\SiteAnalytics\GeoIp\CountryDatabaseUpdater;

/**
 * Downloads the latest country database. Run it monthly, or click "Update now" on the System page.
 */
final class GeoIpUpdateCommand extends Command
{
    protected $signature = 'site-analytics:geoip-update';

    protected $description = 'Download the latest IP-to-country database from DB-IP';

    public function handle(CountryDatabaseUpdater $updater, CountryDatabase $database): int
    {
        try {
            $updater->update();
        } catch (CountryDatabaseUpdateFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Installed the country database built %s (%s) at %s.',
            $database->builtAt()?->toDateString(),
            round((int) $database->size() / 1024 / 1024, 1).' MB',
            $database->path(),
        ));

        return self::SUCCESS;
    }
}
