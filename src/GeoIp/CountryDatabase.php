<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\GeoIp;

use Carbon\CarbonImmutable;
use MaxMind\Db\Reader;
use Throwable;

/**
 * The local IP-to-country database (MaxMind DB format, e.g. DB-IP Lite Country). Lookups never leave the server.
 * A missing or broken file is not an error: lookups then return null.
 */
final class CountryDatabase
{
    private ?Reader $reader = null;

    public function path(): string
    {
        return (string) config('site-analytics.geoip.database');
    }

    public function exists(): bool
    {
        return $this->reader() !== null;
    }

    /**
     * The ISO 3166-1 alpha-2 code of the IP's country, or null for private, reserved and unknown addresses.
     */
    public function country(?string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            $code = $this->reader()?->get($ip)['country']['iso_code'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }

    public function builtAt(): ?CarbonImmutable
    {
        $epoch = $this->reader()?->metadata()->buildEpoch;

        return $epoch === null ? null : CarbonImmutable::createFromTimestamp($epoch);
    }

    /**
     * Missing, or built longer ago than the configured max_age_days.
     */
    public function isOutdated(): bool
    {
        $builtAt = $this->builtAt();

        return $builtAt === null || $builtAt->lt(CarbonImmutable::now()->subDays((int) config('site-analytics.geoip.max_age_days', 35)));
    }

    /**
     * @return 'missing'|'outdated'|'ok'
     */
    public function state(): string
    {
        return match (true) {
            ! $this->exists() => 'missing',
            $this->isOutdated() => 'outdated',
            default => 'ok',
        };
    }

    public function size(): ?int
    {
        return $this->exists() ? (int) filesize($this->path()) : null;
    }

    /**
     * Close the open file, so the next lookup reads a replaced database.
     */
    public function forget(): void
    {
        $this->reader?->close();
        $this->reader = null;
    }

    private function reader(): ?Reader
    {
        if ($this->reader !== null || ! is_file($this->path())) {
            return $this->reader;
        }

        try {
            return $this->reader = new Reader($this->path());
        } catch (Throwable) {
            return null;
        }
    }
}
