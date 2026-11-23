@php
    $locale = app()->getLocale();
    $date = fn ($value): string => \Carbon\CarbonImmutable::parse($value)->locale($locale)->translatedFormat(__('M j, Y'));
    $ago = fn ($value): string => \Carbon\CarbonImmutable::parse($value)->locale($locale)->diffForHumans();
    $yesNo = fn (bool $value): string => $value ? __('Yes') : __('No');
    $flash = session('site-analytics.flash');
@endphp

<x-site-analytics::layout :title="__('System')" :description="__('What is recorded and how, and the tools that keep it running.')">
    @if($flash)
        <p @class(['sa-alert', 'sa-alert-error' => $flash['type'] === 'error']) role="status" data-flash="{{ $flash['type'] }}">{{ $flash['message'] }}</p>
    @endif

    <x-site-analytics::panel :title="__('Tracking')" data-panel="tracking">
        <x-slot:actions>
            @if($tracking['enabled'])
                <span class="sa-badge sa-badge-ok">{{ __('On') }}</span>
            @else
                <span class="sa-badge sa-badge-error">{{ __('Off') }}</span>
            @endif
        </x-slot:actions>

        <dl class="sa-facts">
            <dt>{{ __('Last page view') }}</dt>
            <dd data-last-page-view>{{ $tracking['last_page_view'] ? $ago($tracking['last_page_view']) : __('None yet') }}</dd>

            <dt>{{ __('Recorded pages') }}</dt>
            <dd>{{ $tracking['group'] ? __('Every page of the :group middleware group', ['group' => $tracking['group']]) : __('Routes with the site-analytics middleware') }}</dd>

            <dt>{{ __('Excluded paths') }}</dt>
            <dd>{{ $tracking['exclude'] ? implode(', ', $tracking['exclude']) : __('None') }}</dd>

            <dt>{{ __('Ignored IP addresses') }}</dt>
            <dd>{{ $tracking['ignored_ips'] ? implode(', ', $tracking['ignored_ips']) : __('None') }}</dd>

            <dt>{{ __('Ignore bots') }}</dt>
            <dd>{{ $yesNo($tracking['ignore_robots']) }}</dd>

            <dt>{{ __('Ignore logged-in users') }}</dt>
            <dd>
                {{ $yesNo($tracking['ignore_authenticated']) }}
                @if($tracking['ignore_authenticated'])
                    <span class="sa-muted">· {{ __('Your own visits are not counted while you are logged in.') }}</span>
                @endif
            </dd>
        </dl>
        <p class="sa-note">{{ __('Change these in config/site-analytics.php.') }}</p>
    </x-site-analytics::panel>

    <x-site-analytics::panel :title="__('Country Lookup')" data-panel="geoip" data-geoip-state="{{ $geoip['state'] }}">
        <x-slot:actions>
            <span @class(['sa-badge', 'sa-badge-ok' => $geoip['state'] === 'ok', 'sa-badge-error' => $geoip['state'] === 'missing'])>
                {{ ['ok' => __('Up to date'), 'outdated' => __('Outdated'), 'missing' => __('Not installed')][$geoip['state']] }}
            </span>
        </x-slot:actions>

        @if($geoip['state'] === 'missing')
            <p class="sa-lead">{{ __('Countries are looked up in a local copy of DB-IP\'s free database, so visitor IPs never leave the server. It is not installed yet, so no countries are recorded.') }}</p>
        @elseif($geoip['state'] === 'outdated')
            <p class="sa-lead">{{ __('The database is older than :days days. Countries of recently reassigned IP addresses may be wrong.', ['days' => $geoip['max_age_days']]) }}</p>
        @endif

        <dl class="sa-facts">
            @if($geoip['built_at'])
                <dt>{{ __('Database') }}</dt>
                <dd data-geoip-built-at="{{ $geoip['built_at']->toDateString() }}">{{ __('DB-IP Lite Country from :date', ['date' => $date($geoip['built_at'])]) }} · {{ round($geoip['size'] / 1024 / 1024, 1) }} MB</dd>
            @endif

            <dt>{{ __('Last update') }}</dt>
            <dd>{{ $geoip['updated_at'] ? $ago($geoip['updated_at']) : __('Never') }}</dd>

            @if($geoip['last_error'])
                <dt>{{ __('Last error') }}</dt>
                <dd class="sa-error" data-geoip-error>{{ $geoip['last_error'] }} <span class="sa-muted">· {{ $ago($geoip['last_attempt_at']) }}</span></dd>
            @endif

            <dt>{{ __('Automatic updates') }}</dt>
            <dd>{{ $geoip['auto_update'] ? __('On, after :days days', ['days' => $geoip['max_age_days']]) : __('Off') }}</dd>

            <dt>{{ __('Your request') }}</dt>
            <dd data-your-country>
                {{ $geoip['your_ip'] }} → {{ $geoip['your_country'] ?? __('Unknown') }}
                @if(! $geoip['your_country'] && $geoip['state'] !== 'missing')
                    <span class="sa-muted">· {{ __('Private or unknown address. Behind a proxy? Configure TrustProxies, or every visitor gets the proxy\'s country.') }}</span>
                @endif
            </dd>

            <dt>{{ __('Page views with a country') }}</dt>
            <dd data-geoip-coverage="{{ $geoip['coverage'] ?? '' }}">{{ $geoip['coverage'] === null ? __('No page views in the last 7 days') : __(':percent% in the last 7 days', ['percent' => $geoip['coverage']]) }}</dd>

            <dt>{{ __('File') }}</dt>
            <dd class="sa-code">{{ $geoip['path'] }}</dd>
        </dl>

        <div class="sa-actions">
            @if($canManage)
                <form method="POST" action="{{ route(config('site-analytics.routes.name').'system.geoip') }}">
                    @csrf
                    <button type="submit" class="sa-button" data-action="geoip-update">{{ $geoip['state'] === 'missing' ? __('Install now') : __('Update now') }}</button>
                </form>
            @endif
            <span class="sa-muted">{{ $canManage ? __('or run') : __('Run') }} <code class="sa-code">php artisan site-analytics:geoip-update</code></span>
        </div>

        <p class="sa-note">{!! __('IP geolocation by :link', ['link' => '<a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>']) !!}</p>
    </x-site-analytics::panel>
</x-site-analytics::layout>
