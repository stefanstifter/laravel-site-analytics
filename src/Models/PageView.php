<?php

namespace Stifter\SiteAnalytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stifter\SiteAnalytics\Database\Factories\PageViewFactory;

/**
 * One recorded page view. The "country" column is kept for existing data but no longer filled.
 *
 * @property string $uri
 * @property string|null $session
 * @property string|null $visitor
 * @property string|null $source
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
