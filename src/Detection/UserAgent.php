<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Detection;

use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Classifies a user agent string as robot or device type, and names its browser.
 */
final class UserAgent
{
    public const DESKTOP = 'desktop';

    public const PHONE = 'phone';

    public const TABLET = 'tablet';

    public const ROBOT = 'robot';

    private static ?CrawlerDetect $crawlerDetect = null;

    private ?bool $isRobot = null;

    public function __construct(private readonly string $userAgent) {}

    public static function fromRequest(Request $request): self
    {
        return new self((string) $request->userAgent());
    }

    public function isRobot(): bool
    {
        return $this->isRobot ??= self::crawlerDetect()->isCrawler($this->userAgent);
    }

    /**
     * One of desktop, phone, tablet or robot. iPads with iPadOS 13+ identify as a Mac and count as desktop.
     */
    public function device(): string
    {
        return match (true) {
            $this->isRobot() => self::ROBOT,
            $this->matches('/iPad|Tablet(?!.*PC)|Kindle|Silk|PlayBook|Android(?!.*Mobile)/i') => self::TABLET,
            $this->matches('/Mobile|iPhone|iPod|Android|Windows Phone|BlackBerry|BB10|Opera Mini/i') => self::PHONE,
            default => self::DESKTOP,
        };
    }

    /**
     * Chrome, Safari, Firefox, Edge or Other. Chromium-based browsers without their own entry count as Chrome.
     */
    public function browser(): string
    {
        return match (true) {
            $this->matches('/\bEdg(e|A|iOS)?\//') => 'Edge',
            $this->matches('/\b(Firefox|FxiOS)\//') => 'Firefox',
            $this->matches('/\b(Chrome|CriOS)\//') => 'Chrome',
            $this->matches('/\bSafari\//') => 'Safari',
            default => 'Other',
        };
    }

    private function matches(string $pattern): bool
    {
        return preg_match($pattern, $this->userAgent) === 1;
    }

    private static function crawlerDetect(): CrawlerDetect
    {
        return self::$crawlerDetect ??= new CrawlerDetect([], '');
    }
}
