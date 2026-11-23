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

    <div class="sa-grid">
        @foreach([
            ['title' => __('Top :count Search Terms', ['count' => $termLimit]), 'rows' => $topTerms, 'key' => 'top-terms', 'count' => 'searches', 'countLabel' => __('Searches'), 'empty' => __('No search terms in this period.')],
            ['title' => __('Search Terms Without Results'), 'rows' => $termsWithoutResults, 'key' => 'terms-without-results', 'count' => 'without_results', 'countLabel' => __('No results'), 'empty' => __('Every search found something.')],
        ] as $table)
            <x-site-analytics::panel :title="$table['title']" :padded="false" data-table="{{ $table['key'] }}">
                <x-site-analytics::table>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Term') }}</th>
                            <th scope="col" class="sa-num">{{ $table['countLabel'] }}</th>
                            <th scope="col" class="sa-num">{{ __('Visitors') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($table['rows'] as $row)
                            <tr data-term="{{ $row->term }}" data-count="{{ $row->{$table['count']} }}" data-searchers="{{ $row->searchers }}">
                                <td>
                                    {{ $row->term }}
                                    @if($table['key'] === 'top-terms' && (int) $row->without_results === (int) $row->searches)
                                        <span class="sa-badge">{{ __('No results') }}</span>
                                    @endif
                                </td>
                                <td class="sa-num">{{ number_format($row->{$table['count']}) }}</td>
                                <td class="sa-num">{{ number_format($row->searchers) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="sa-table-empty">{{ $table['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-site-analytics::table>
            </x-site-analytics::panel>
        @endforeach
    </div>

    <x-site-analytics::panel :title="__('Filters Used')" data-filter-usage>
        @if($filterUsage->isEmpty())
            <x-site-analytics::empty>{{ __('No filters used in this period.') }}</x-site-analytics::empty>
        @else
            <div class="sa-filters">
                @foreach($filterUsage as $filter)
                    <div data-filter="{{ $filter['label'] }}">
                        <h3 class="sa-filter-name">{{ $filter['label'] }}</h3>
                        <div class="sa-stack">
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
        <x-site-analytics::table>
            <tbody>
                @forelse($combinations as $combination)
                    <tr data-combination="{{ $combination['label'] }}" data-count="{{ $combination['searches'] }}">
                        <td>{{ $combination['label'] }}</td>
                        <td class="sa-num">{{ number_format($combination['searches']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="sa-table-empty">{{ __('No filters used in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-site-analytics::table>
    </x-site-analytics::panel>
</x-site-analytics::layout>
