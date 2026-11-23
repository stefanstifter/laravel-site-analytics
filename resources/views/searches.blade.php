<x-site-analytics::layout :title="__('Searches')" :period="$period" :description="__('What visitors search and filter for, and which searches find nothing.')">
    <x-site-analytics::stats :count="3">
        <x-site-analytics::stat :label="__('Searches')" :value="number_format($summary['searches'])" :hint="__('Search terms and filter changes')" data-summary-searches="{{ $summary['searches'] }}" />
        <x-site-analytics::stat :label="__('Visitors Searching')" :value="number_format($summary['searchers'])" data-summary-searchers="{{ $summary['searchers'] }}" />
        <x-site-analytics::stat
            :label="__('Without Results')"
            :value="$summary['without_results_share'] === null ? '–' : $summary['without_results_share'].'%'"
            :hint="trans_choice(':count search found nothing|:count searches found nothing', $summary['without_results'], ['count' => number_format($summary['without_results'])])"
            data-summary-without-results="{{ $summary['without_results'] }}"
        />
    </x-site-analytics::stats>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach([
            ['title' => __('Top :count Search Terms', ['count' => $termLimit]), 'rows' => $topTerms, 'key' => 'top-terms', 'count' => 'searches', 'countLabel' => __('Searches'), 'empty' => __('No search terms in this period.')],
            ['title' => __('Search Terms Without Results'), 'rows' => $termsWithoutResults, 'key' => 'terms-without-results', 'count' => 'without_results', 'countLabel' => __('No results'), 'empty' => __('Every search found something.')],
        ] as $table)
            <x-site-analytics::panel :title="$table['title']" :padded="false" data-table="{{ $table['key'] }}">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left font-medium">{{ __('Term') }}</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-right font-medium">{{ $table['countLabel'] }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-medium">{{ __('Visitors') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        @forelse($table['rows'] as $row)
                            <tr data-term="{{ $row->term }}" data-count="{{ $row->{$table['count']} }}" data-searchers="{{ $row->searchers }}">
                                <td class="w-full break-words px-6 py-3 text-zinc-900 dark:text-white">
                                    {{ $row->term }}
                                    @if($table['key'] === 'top-terms' && (int) $row->without_results === (int) $row->searches)
                                        <flux:badge size="sm" color="amber" class="ml-2">{{ __('No results') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($row->{$table['count']}) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($row->searchers) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-6 text-center text-zinc-500 dark:text-zinc-400">{{ $table['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-site-analytics::panel>
        @endforeach
    </div>

    <x-site-analytics::panel :title="__('Filters Used')" data-filter-usage>
        @if($filterUsage->isEmpty())
            <x-site-analytics::empty>{{ __('No filters used in this period.') }}</x-site-analytics::empty>
        @else
            <div class="grid grid-cols-1 gap-x-10 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($filterUsage as $filter)
                    <div data-filter="{{ $filter['label'] }}">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $filter['label'] }}</div>
                        <div class="mt-3 space-y-3">
                            @foreach($filter['values'] as $value)
                                <x-site-analytics::bar
                                    :label="$value['label']"
                                    :value="number_format($value['searches'])"
                                    :share="$value['share']"
                                    data-filter-value="{{ $value['label'] }}"
                                    data-filter-searches="{{ $value['searches'] }}"
                                />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-site-analytics::panel>

    <x-site-analytics::panel :title="__('Common Filter Combinations')" :padded="false" data-table="combinations">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                @forelse($combinations as $combination)
                    <tr data-combination="{{ $combination['label'] }}" data-count="{{ $combination['searches'] }}">
                        <td class="px-6 py-3 text-zinc-900 dark:text-white">{{ $combination['label'] }}</td>
                        <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($combination['searches']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-6 text-center text-zinc-500 dark:text-zinc-400">{{ __('No filters used in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-site-analytics::panel>
</x-site-analytics::layout>
