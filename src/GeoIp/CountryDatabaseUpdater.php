<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\GeoIp;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\File;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Downloads DB-IP's free country database (CC BY 4.0, published monthly) and swaps it in atomically.
 * Remembers the last attempt and its error in a JSON file next to the database, which survives cache clears.
 */
final class CountryDatabaseUpdater
{
    public const DOWNLOAD_URL = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    private const RETRY_HOURS = 24;

    public function __construct(
        private readonly CountryDatabase $database,
        private readonly Http $http,
    ) {}

    /**
     * Download and install this month's database, or last month's if this month's is not out yet.
     *
     * @throws CountryDatabaseUpdateFailed
     */
    public function update(): void
    {
        File::ensureDirectoryExists(dirname($this->database->path()));
        $lock = fopen($this->database->path().'.lock', 'c');

        if ($lock === false) {
            throw new CountryDatabaseUpdateFailed('Cannot write to '.dirname($this->database->path()).'.');
        }

        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            throw new CountryDatabaseUpdateFailed('Another update is already running.');
        }

        try {
            $url = $this->install();
            $this->writeStatus(['updated_at' => CarbonImmutable::now()->toIso8601String(), 'source' => $url, 'last_error' => null]);
        } catch (Throwable $exception) {
            $this->writeStatus(['last_error' => $exception->getMessage()]);

            throw $exception instanceof CountryDatabaseUpdateFailed
                ? $exception
                : new CountryDatabaseUpdateFailed($exception->getMessage(), previous: $exception);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Update when auto_update is on and the database is outdated. Failed attempts are retried a day later at the earliest.
     */
    public function updateIfDue(): void
    {
        if (! config('site-analytics.geoip.auto_update')
            || ! $this->database->isOutdated()
            || $this->status()['last_attempt_at']?->gt(CarbonImmutable::now()->subHours(self::RETRY_HOURS))) {
            return;
        }

        try {
            $this->update();
        } catch (CountryDatabaseUpdateFailed $exception) {
            report($exception);
        }
    }

    /**
     * @return array{updated_at: ?CarbonImmutable, last_attempt_at: ?CarbonImmutable, last_error: ?string, source: ?string}
     */
    public function status(): array
    {
        $status = $this->readStatus();
        $date = fn (string $key): ?CarbonImmutable => is_string($status[$key] ?? null) ? CarbonImmutable::parse($status[$key]) : null;

        return [
            'updated_at' => $date('updated_at'),
            'last_attempt_at' => $date('last_attempt_at'),
            'last_error' => is_string($status['last_error'] ?? null) ? $status['last_error'] : null,
            'source' => is_string($status['source'] ?? null) ? $status['source'] : null,
        ];
    }

    /**
     * @return string The URL the installed database came from.
     */
    private function install(): string
    {
        $this->writeStatus(['last_attempt_at' => CarbonImmutable::now()->toIso8601String()]);

        [$url, $compressed] = $this->download();
        $contents = @gzdecode($compressed);

        if ($contents === false) {
            throw new CountryDatabaseUpdateFailed("The download from {$url} is not a gzip file.");
        }

        $download = $this->database->path().'.download';
        File::put($download, $contents);

        try {
            $this->validate($download);
        } catch (Throwable $exception) {
            File::delete($download);

            throw new CountryDatabaseUpdateFailed("The download from {$url} is not a country database: {$exception->getMessage()}", previous: $exception);
        }

        File::move($download, $this->database->path());
        $this->database->forget();

        return $url;
    }

    /**
     * @return array{0: string, 1: string} The URL and the gzipped database.
     */
    private function download(): array
    {
        foreach ([CarbonImmutable::now(), CarbonImmutable::now()->subMonthNoOverflow()] as $month) {
            $url = sprintf(self::DOWNLOAD_URL, $month->format('Y-m'));
            $response = $this->http->timeout(120)->get($url);

            if ($response->notFound()) {
                continue;
            }

            if ($response->failed()) {
                throw new CountryDatabaseUpdateFailed("Downloading {$url} failed with HTTP {$response->status()}.");
            }

            return [$url, $response->body()];
        }

        throw new CountryDatabaseUpdateFailed('DB-IP has no database for this or last month.');
    }

    private function validate(string $path): void
    {
        $reader = new Reader($path);
        $type = $reader->metadata()->databaseType;
        $reader->close();

        if (! str_contains($type, 'Country')) {
            throw new CountryDatabaseUpdateFailed("it is a {$type} database.");
        }
    }

    /**
     * @param  array<string, string|null>  $changes
     */
    private function writeStatus(array $changes): void
    {
        File::put($this->statusPath(), json_encode([...$this->readStatus(), ...$changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStatus(): array
    {
        $status = is_file($this->statusPath()) ? json_decode((string) file_get_contents($this->statusPath()), true) : null;

        return is_array($status) ? $status : [];
    }

    private function statusPath(): string
    {
        return preg_replace('/\.mmdb$/', '', $this->database->path()).'.json';
    }
}
