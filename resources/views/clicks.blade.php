@php
    $clicksRoute = config('site-analytics.routes.name').'clicks';
    $clicksUrl = fn (array $filters = []): string => route($clicksRoute, ['period' => $period, ...$filters]);
    $groupLabel = fn (?string $name): string => $name ?? __('Ungrouped');
    $percent = fn (?int $value): string => $value === null ? '–' : $value.'%';
@endphp

<x-site-analytics::layout :title="__('Clicks')" :period="$period" :description="__('Which links and buttons visitors click, and on which pages.')">
    @unless($hasClicks)
        <x-site-analytics::panel :title="__('No clicks recorded yet')" data-clicks-setup>
            <div class="sa-prose">
                <p>{{ __('Add the script to the layout of your public pages, once:') }}</p>
                <pre class="sa-pre"><code>&lt;x-site-analytics::script /&gt;</code></pre>
                <p>{{ __('Then mark what to count. Every link and button inside a group is counted, labelled with its text:') }}</p>
                <pre class="sa-pre"><code>&lt;section data-track-group="Side projects"&gt;
    &lt;a href="https://example.com"&gt;AI Agent&lt;/a&gt;
&lt;/section&gt;

&lt;a href="/contact" data-track="Hero CTA"&gt;Get in touch&lt;/a&gt;</code></pre>
                @if(config('site-analytics.clicks.outbound', true))
                    <p>{{ __('Links to other sites, mailto: and tel: are counted without markup. Use data-track="false" to leave something out.') }}</p>
                @else
                    <p>{{ __('Use data-track="false" to leave something out.') }}</p>
                @endif
                @if(config('site-analytics.ignore_authenticated'))
                    <p class="sa-muted">{{ __('Clicks are not recorded while you are logged in, so log out or use a private window to try it.') }}</p>
                @endif
            </div>
        </x-site-analytics::panel>
    @else
        @if($grouped || $link !== null)
            <nav class="sa-crumbs" aria-label="{{ __('Filter') }}" data-clicks-filter>
                <a href="{{ $clicksUrl() }}" wire:navigate>{{ __('All clicks') }}</a>
                @if($grouped)
                    <span aria-hidden="true">›</span>
                    @if($link !== null)
                        <a href="{{ $clicksUrl(['group' => $group ?? '']) }}" wire:navigate data-crumb-group>{{ $groupLabel($group) }}</a>
                    @else
                        <span aria-current="page" data-crumb-group>{{ $groupLabel($group) }}</span>
                    @endif
                @endif
                @if($link !== null)
                    <span aria-hidden="true">›</span>
                    <span aria-current="page" data-crumb-link>{{ $linkDetails->label ?? $link }}</span>
                    @if($linkDetails?->href && ! str_starts_with($linkDetails->href, 'mailto:') && ! str_starts_with($linkDetails->href, 'tel:'))
                        <a href="{{ $linkDetails->target_host ? $linkDetails->href : url($linkDetails->href) }}" target="_blank" rel="noopener" class="sa-muted sa-code">{{ $linkDetails->href }} ↗</a>
                    @elseif($linkDetails?->href)
                        <span class="sa-muted sa-code">{{ $linkDetails->href }}</span>
                    @endif
                @endif
            </nav>
        @endif

        <x-site-analytics::stats :count="3">
            <x-site-analytics::stat :label="__('Clicks')" :value="number_format($summary['clicks'])" data-summary-clicks="{{ $summary['clicks'] }}" />
            <x-site-analytics::stat :label="__('Visitors Clicking')" :value="number_format($summary['visitors'])" data-summary-clickers="{{ $summary['visitors'] }}" />
            <x-site-analytics::stat
                :label="__('Click Rate')"
                :value="$percent($summary['click_rate'])"
                :hint="trans_choice('of :count visitor to these pages|of :count visitors to these pages', $summary['page_visitors'], ['count' => number_format($summary['page_visitors'])])"
                data-summary-click-rate="{{ $summary['click_rate'] ?? '' }}"
            />
        </x-site-analytics::stats>

        <x-site-analytics::panel :title="__('Clicks per Day')" :padded="false" data-chart="daily-clicks">
            <x-site-analytics::line-chart
                :chart="$chart"
                :label="__('Line chart of clicks per day from :start to :end.', ['start' => $days->first()['label'], 'end' => $days->last()['label']])"
            />
        </x-site-analytics::panel>

        @unless($grouped)
            <x-site-analytics::panel :title="__('Groups')" :padded="false" data-table="groups">
                <x-site-analytics::table>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Group') }}</th>
                            <th scope="col" class="sa-num">{{ __('Links') }}</th>
                            <th scope="col" class="sa-num">{{ __('Clicks') }}</th>
                            <th scope="col" class="sa-num">{{ __('Visitors') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $row)
                            <tr data-group="{{ $row['group'] ?? '' }}" data-clicks="{{ $row['clicks'] }}" data-visitors="{{ $row['visitors'] }}">
                                <td>
                                    <a href="{{ $clicksUrl(['group' => $row['group'] ?? '']) }}" wire:navigate @class(['sa-link', 'sa-muted' => $row['group'] === null])>{{ $groupLabel($row['group']) }}</a>
                                    <div class="sa-bar-track"><div class="sa-bar-fill" style="width: {{ $row['share'] }}%"></div></div>
                                </td>
                                <td class="sa-num">{{ number_format($row['links']) }}</td>
                                <td class="sa-num">{{ number_format($row['clicks']) }}</td>
                                <td class="sa-num">{{ number_format($row['visitors']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="sa-table-empty">{{ __('No clicks in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-site-analytics::table>
            </x-site-analytics::panel>
        @endunless

        @if($link === null)
            <x-site-analytics::panel :title="__('Top :count Links', ['count' => $linkLimit])" :padded="false" data-table="links">
                <x-site-analytics::table>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Link') }}</th>
                            @unless($grouped)
                                <th scope="col">{{ __('Group') }}</th>
                            @endunless
                            <th scope="col" class="sa-num">{{ __('Clicks') }}</th>
                            <th scope="col" class="sa-num">{{ __('Visitors') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($links as $row)
                            @php($rowGroup = $grouped ? $group : $row['group'])
                            <tr data-link="{{ $row['key'] }}" data-clicks="{{ $row['clicks'] }}" data-visitors="{{ $row['visitors'] }}">
                                <td>
                                    <a href="{{ $clicksUrl(['group' => $rowGroup ?? '', 'link' => $row['key']]) }}" wire:navigate class="sa-link">{{ $row['label'] }}</a>
                                    @if($row['href'])
                                        <div class="sa-sub sa-code">{{ $row['target_host'] ? $row['target_host'].' ↗' : $row['href'] }}</div>
                                    @endif
                                    <div class="sa-bar-track"><div class="sa-bar-fill" style="width: {{ $row['share'] }}%"></div></div>
                                </td>
                                @unless($grouped)
                                    <td @class(['sa-muted' => $rowGroup === null])>{{ $groupLabel($rowGroup) }}</td>
                                @endunless
                                <td class="sa-num">{{ number_format($row['clicks']) }}</td>
                                <td class="sa-num">{{ number_format($row['visitors']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $grouped ? 3 : 4 }}" class="sa-table-empty">{{ __('No clicks in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-site-analytics::table>
            </x-site-analytics::panel>
        @endif

        <div class="sa-grid">
            <x-site-analytics::panel :title="__('Pages')" :padded="false" data-table="pages">
                <x-site-analytics::table>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Page') }}</th>
                            <th scope="col" class="sa-num">{{ __('Clicks') }}</th>
                            <th scope="col" class="sa-num" title="{{ __('Share of the page\'s visitors who clicked') }}">{{ __('Click Rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pages as $row)
                            <tr data-page="{{ $row['uri'] }}" data-clicks="{{ $row['clicks'] }}" data-click-rate="{{ $row['click_rate'] ?? '' }}">
                                <td><a href="{{ url($row['uri']) }}" target="_blank" rel="noopener" class="sa-link">{{ $row['uri'] }}</a></td>
                                <td class="sa-num">{{ number_format($row['clicks']) }}</td>
                                <td class="sa-num" title="{{ __(':visitors of :total visitors', ['visitors' => number_format($row['visitors']), 'total' => number_format($row['page_visitors'])]) }}">{{ $percent($row['click_rate']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="sa-table-empty">{{ __('No clicks in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-site-analytics::table>
            </x-site-analytics::panel>

            <div class="sa-stack">
                @if($destinations->isNotEmpty())
                    <x-site-analytics::panel :title="__('Other Sites')" data-panel="destinations">
                        <x-slot:actions><span class="sa-caps">{{ __('Clicks') }}</span></x-slot:actions>
                        <ol class="sa-list">
                            @foreach($destinations as $row)
                                <li data-destination="{{ $row['host'] }}" data-clicks="{{ $row['clicks'] }}">
                                    <x-site-analytics::bar :label="$row['host']" :value="number_format($row['clicks'])" :share="$row['share']" :href="'https://'.$row['host']" />
                                </li>
                            @endforeach
                        </ol>
                    </x-site-analytics::panel>
                @endif

                <x-site-analytics::panel :title="__('Devices')" data-devices>
                    <div class="sa-stack">
                        @forelse($devices as $device)
                            <x-site-analytics::bar
                                :label="$device['label']"
                                :value="($device['share'] === 0 ? '<1' : $device['share']).'% · '.number_format($device['clicks'])"
                                :share="$device['share']"
                                data-device="{{ $device['type'] }}"
                                data-device-clicks="{{ $device['clicks'] }}"
                            />
                        @empty
                            <x-site-analytics::empty>{{ __('No clicks in this period.') }}</x-site-analytics::empty>
                        @endforelse
                    </div>
                </x-site-analytics::panel>
            </div>
        </div>
    @endunless
</x-site-analytics::layout>
