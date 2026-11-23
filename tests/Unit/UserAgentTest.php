<?php

namespace Stifter\SiteAnalytics\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stifter\SiteAnalytics\Detection\UserAgent;

class UserAgentTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function userAgents(): array
    {
        return [
            'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'phone', 'Safari'],
            'iPhone Chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.54 Mobile/15E148 Safari/604.1', 'phone', 'Chrome'],
            'iPhone Instagram in-app' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 330.0.3.12.90 (iPhone15,2; iOS 17_4_1; en_US; en; scale=3.00; 1179x2556; 606162567)', 'phone', 'Other'],
            'Android phone Chrome' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'phone', 'Chrome'],
            'Android phone Samsung Internet' => ['Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36', 'phone', 'Chrome'],
            'Android phone Firefox' => ['Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0', 'phone', 'Firefox'],
            'Android phone Edge' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 EdgA/126.0.2592.61', 'phone', 'Edge'],
            'iPad (iPadOS 12)' => ['Mozilla/5.0 (iPad; CPU OS 12_5_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.2 Mobile/15E148 Safari/604.1', 'tablet', 'Safari'],
            'iPad Chrome' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.54 Mobile/15E148 Safari/604.1', 'tablet', 'Chrome'],
            'iPad (iPadOS 13+, desktop mode)' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'desktop', 'Safari'],
            'Android tablet Chrome' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'tablet', 'Chrome'],
            'Android tablet Firefox' => ['Mozilla/5.0 (Android 13; Tablet; rv:127.0) Gecko/127.0 Firefox/127.0', 'tablet', 'Firefox'],
            'Kindle Fire Silk' => ['Mozilla/5.0 (Linux; Android 9; KFTRWI) AppleWebKit/537.36 (KHTML, like Gecko) Silk/126.3.1 like Chrome/126.0.6478.71 Safari/537.36', 'tablet', 'Chrome'],
            'Windows Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome'],
            'Windows Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'desktop', 'Edge'],
            'Windows Firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0', 'desktop', 'Firefox'],
            'Windows touch laptop (Tablet PC)' => ['Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; Tablet PC 2.0; rv:11.0) like Gecko', 'desktop', 'Other'],
            'Mac Firefox' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.5; rv:127.0) Gecko/20100101 Firefox/127.0', 'desktop', 'Firefox'],
            'Linux Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome'],
            'ChromeOS' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome'],
            'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'robot', 'Other'],
            'Googlebot smartphone' => ['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.6478.126 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'robot', 'Chrome'],
            'Bingbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 'robot', 'Chrome'],
            'GPTBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'robot', 'Other'],
            'curl' => ['curl/8.7.1', 'robot', 'Other'],
            'empty' => ['', 'desktop', 'Other'],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_it_detects_device_and_browser(string $userAgent, string $device, string $browser): void
    {
        $agent = new UserAgent($userAgent);

        $this->assertSame($device, $agent->device());
        $this->assertSame($browser, $agent->browser());
        $this->assertSame($device === 'robot', $agent->isRobot());
    }
}
