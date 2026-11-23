<x-site-analytics::layout :title="__('Overview')" :period="$period" :description="__('How many people visit, where they come from and what they look at.')">
    <x-site-analytics::stats :count="2 + (int) isset($summary['website_clicks']) + (int) isset($summary['searches'])">
        <x-site-analytics::stat :label="__('Visitors')" :value="number_format($summary['total_visitors'])" :hint="__('Sum of daily visitors')" data-summary-visitors="{{ $summary['total_visitors'] }}" />
        <x-site-analytics::stat :label="__('Page Views')" :value="number_format($summary['page_views'])" data-summary-page-views="{{ $summary['page_views'] }}" />
        @isset($summary['website_clicks'])
            <x-site-analytics::stat :label="__('Website Clicks')" :value="number_format($summary['website_clicks'])" :hint="__('Visitors sent to external sites')" data-summary-website-clicks="{{ $summary['website_clicks'] }}" />
        @endisset
        @isset($summary['searches'])
            <x-site-analytics::stat :label="__('Searches')" :value="number_format($summary['searches'])" data-summary-searches="{{ $summary['searches'] }}" />
        @endisset
    </x-site-analytics::stats>

    <x-site-analytics::panel :title="__('Visitors per Day')" :padded="false" data-chart="daily-visitors">
        <x-slot:actions>
            <span class="text-zinc-500 dark:text-zinc-400">
                <span data-summary-average-daily="{{ $summary['average_daily_visitors'] }}">{{ __('Avg :count / day', ['count' => number_format($summary['average_daily_visitors'], $summary['average_daily_visitors'] >= 10 ? 0 : 1)]) }}</span>
                @if($summary['peak_day'] && $summary['peak_visitors'] > 0)
                    · <span data-summary-peak-visitors="{{ $summary['peak_visitors'] }}">{{ __('Peak :count on :day', ['count' => number_format($summary['peak_visitors']), 'day' => $summary['peak_day']]) }}</span>
                @endif
            </span>
        </x-slot:actions>

        <x-site-analytics::line-chart
            :chart="$chart"
            :label="__('Line chart of visitors per day from :start to :end. Exact values are in the daily breakdown.', ['start' => $days->first()['label'], 'end' => $days->last()['label']])"
        />
    </x-site-analytics::panel>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-site-analytics::panel :title="__('Top Pages')" data-panel="pages">
            <x-slot:actions><span class="text-xs uppercase tracking-wider text-zinc-400">{{ __('Visitors') }}</span></x-slot:actions>
            <x-site-analytics::ranked-list :items="$pages" :empty="__('No page views in this period.')" :link="fn (string $uri): string => url($uri)" />
        </x-site-analytics::panel>

        <x-site-analytics::panel :title="__('Referrers')" data-panel="referrers">
            <x-slot:actions><span class="text-xs uppercase tracking-wider text-zinc-400">{{ __('Visitors') }}</span></x-slot:actions>
            <x-site-analytics::ranked-list :items="$referrers" :empty="__('No visitors arrived from other sites in this period.')" :link="fn (string $host): ?string => str_contains($host, '://') ? null : 'https://'.$host" />
        </x-site-analytics::panel>

        <x-site-analytics::panel :title="__('Devices')" data-devices>
            <div class="space-y-3">
                @forelse($devices as $device)
                    <x-site-analytics::bar
                        :label="$device['label']"
                        :value="($device['share'] === 0 && $device['visitors'] > 0 ? '<1' : $device['share']).'% · '.number_format($device['visitors'])"
                        :share="$device['share']"
                        data-device="{{ $device['type'] }}"
                        data-device-visitors="{{ $device['visitors'] }}"
                        data-device-share="{{ $device['share'] }}"
                    />
                @empty
                    <x-site-analytics::empty>{{ __('No visits in this period.') }}</x-site-analytics::empty>
                @endforelse
            </div>
        </x-site-analytics::panel>

        <x-site-analytics::panel :title="__('Campaigns')" data-panel="campaigns">
            <x-slot:actions><span class="text-xs uppercase tracking-wider text-zinc-400">{{ __('Visitors') }}</span></x-slot:actions>
            <x-site-analytics::ranked-list :items="$campaigns" :empty="__('No visits from links with UTM tags in this period.')" />
        </x-site-analytics::panel>
    </div>

    <details class="group overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" data-daily-breakdown>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 text-sm font-medium uppercase tracking-wider text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            {{ __('Daily Breakdown') }}
            <flux:icon.chevron-down variant="micro" class="size-4 transition group-open:rotate-180" />
        </summary>

        <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left font-medium">{{ __('Date') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-medium">{{ __('Visitors') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-medium">{{ __('Page Views') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-medium" title="{{ __('Share of visitors on a phone or tablet') }}">{{ __('Mobile') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach($days->reverse() as $day)
                        <tr data-day="{{ $day['date'] }}" data-visitors="{{ $day['visitors'] }}" data-page-views="{{ $day['page_views'] }}">
                            <td class="whitespace-nowrap px-6 py-3 text-zinc-900 dark:text-white">{{ $day['label'] }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($day['visitors']) }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($day['page_views']) }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300" data-mobile-share="{{ $day['mobile_share'] ?? '' }}">{{ $day['mobile_share'] === null ? '–' : $day['mobile_share'].'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</x-site-analytics::layout>
