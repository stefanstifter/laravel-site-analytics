<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Reports;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * SVG geometry for the line-chart component: one value per day, with a tooltip line for each point.
 */
final class DailyChart
{
    private const WIDTH = 800;

    private const HEIGHT = 260;

    private const LEFT = 44;

    private const TOP = 16;

    private const TOOLTIP_WIDTH = 150;

    /**
     * @param  Collection<int, array{date: string, label: string}>  $days  One entry per day, oldest first.
     * @param  string  $key  The plotted value in each day, e.g. "visitors".
     * @param  Closure(array<string, mixed>): string  $tooltip  The tooltip's second line for a day, e.g. "12 visitors · 30 views".
     * @return array{width: int, height: int, top: int, bottom: int, left: int, right: int, line_path: string, area_path: string, ticks: array<int, array{value: int, y: float}>, points: array<int, array{x: float, y: float, date: string, label: string, value: int, tooltip: string, tooltip_x: float, tooltip_y: float}>, x_labels: array<int, array{x: float, label: string, anchor: string}>}
     */
    public static function build(Collection $days, string $key, Closure $tooltip): array
    {
        $left = self::LEFT;
        $right = self::WIDTH - 12;
        $top = self::TOP;
        $bottom = self::HEIGHT - 28;

        $maxValue = self::niceCeiling((int) $days->max($key));
        $count = $days->count();
        $step = $count > 1 ? ($right - $left) / ($count - 1) : 0;

        $points = $days->values()->map(function (array $day, int $index) use ($key, $tooltip, $left, $right, $top, $bottom, $step, $maxValue, $count): array {
            $x = $count > 1 ? $left + $index * $step : ($left + $right) / 2;
            $y = $bottom - ((int) $day[$key] / $maxValue) * ($bottom - $top);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'date' => $day['date'],
                'label' => $day['label'],
                'value' => (int) $day[$key],
                'tooltip' => $tooltip($day),
                'tooltip_x' => round(min(max($x - self::TOOLTIP_WIDTH / 2, $left), $right - self::TOOLTIP_WIDTH), 2),
                'tooltip_y' => round(max($y - 58, 0), 2),
            ];
        });

        $linePath = $points
            ->map(fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])
            ->implode(' ');
        $areaPath = $points->isEmpty()
            ? ''
            : $linePath.' L'.$points->last()['x'].' '.$bottom.' L'.$points->first()['x'].' '.$bottom.' Z';

        $ticks = collect(range(0, 4))->map(fn (int $index): array => [
            'value' => (int) ($maxValue / 4 * $index),
            'y' => round($bottom - ($bottom - $top) / 4 * $index, 2),
        ])->all();

        $xLabels = collect(array_unique([0, intdiv($count - 1, 2), $count - 1]))->map(fn (int $index): array => [
            'x' => $points[$index]['x'],
            'label' => CarbonImmutable::parse($points[$index]['date'])->locale(app()->getLocale())->translatedFormat(__('M j')),
            'anchor' => match (true) {
                $index === 0 => 'start',
                $index === $count - 1 => 'end',
                default => 'middle',
            },
        ])->values()->all();

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
            'line_path' => $linePath,
            'area_path' => $areaPath,
            'ticks' => $ticks,
            'points' => $points->all(),
            'x_labels' => $xLabels,
        ];
    }

    /**
     * Round the axis maximum up to a readable value that splits into four integer gridline steps.
     */
    private static function niceCeiling(int $value): int
    {
        if ($value <= 4) {
            return 4;
        }

        $rawStep = $value / 4;
        $magnitude = 10 ** (int) floor(log10($rawStep));

        foreach ([1, 1.25, 1.5, 2, 2.5, 3, 4, 5, 6, 7.5, 8, 10] as $multiplier) {
            $step = (int) ceil($multiplier * $magnitude);

            if ($step >= $rawStep) {
                return $step * 4;
            }
        }

        return (int) ceil($rawStep) * 4;
    }
}
