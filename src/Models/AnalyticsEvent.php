<?php

namespace Stifter\SiteAnalytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stifter\SiteAnalytics\Database\Factories\AnalyticsEventFactory;

/**
 * Something a visitor did besides viewing a page, e.g. an outbound click or a search, optionally about a subject model.
 *
 * @property array<string, mixed>|null $properties
 */
class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use HasFactory;

    public const OUTBOUND_CLICK = 'outbound_click';

    public const SEARCH = 'search';

    protected $fillable = [
        'name',
        'visitor',
        'session',
        'device',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): AnalyticsEventFactory
    {
        return AnalyticsEventFactory::new();
    }
}
