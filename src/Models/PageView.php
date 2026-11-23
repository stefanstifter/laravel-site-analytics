<?php

namespace Stifter\SiteAnalytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stifter\SiteAnalytics\Database\Factories\PageViewFactory;

/**
 * One recorded page view. The legacy "country" column holds names recorded before 0.4 and is no longer filled.
 *
 * @property string $uri
 * @property string|null $session
 * @property string|null $visitor
 * @property string|null $source
 * @property string|null $country_code ISO 3166-1 alpha-2, e.g. "AT"
 * @property string|null $browser
 * @property string|null $device
 * @property string|null $host
 */
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    protected $fillable = [
        'uri',
        'session',
        'visitor',
        'source',
        'country_code',
        'browser',
        'device',
        'host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    protected static function newFactory(): PageViewFactory
    {
        return PageViewFactory::new();
    }
}
