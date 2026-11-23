<?php

namespace Stifter\SiteAnalytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stifter\SiteAnalytics\Database\Factories\ClickFactory;

/**
 * One click on a tracked link or button.
 *
 * @property string|null $group_name The data-track-group the link sits in, null for ungrouped links.
 * @property string $label The data-track value, aria-label or visible text.
 * @property string|null $href Where the link goes: a path for links within the site, the full URL otherwise. Null for buttons.
 * @property string|null $target_host The other site's host without "www.", null for links within the site, mailto: and tel:.
 * @property string $uri The page the click happened on.
 * @property string|null $visitor
 * @property string|null $session
 * @property string|null $device
 * @property string|null $country_code
 */
class Click extends Model
{
    /** @use HasFactory<ClickFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'analytics_clicks';

    protected $fillable = [
        'group_name',
        'label',
        'href',
        'target_host',
        'uri',
        'visitor',
        'session',
        'device',
        'country_code',
    ];

    /**
     * Clicks on the same link: the same destination within the same group, or the same label for buttons.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForLink(Builder $query, string $link): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('href', $link)
            ->orWhere(fn (Builder $query) => $query->whereNull('href')->where('label', $link)));
    }

    /**
     * Clicks in a group, or ungrouped clicks for null.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInGroup(Builder $query, ?string $group): void
    {
        $group === null ? $query->whereNull('group_name') : $query->where('group_name', $group);
    }

    protected static function newFactory(): ClickFactory
    {
        return ClickFactory::new();
    }
}
