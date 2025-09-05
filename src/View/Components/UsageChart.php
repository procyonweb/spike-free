<?php

namespace Opcodes\Spike\View\Components;

use Asantibanez\LivewireCharts\Facades\LivewireCharts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Facades\Spike;

class UsageChart extends Component
{
    public function render(): View
    {
        $hasUsage = $this->getUsageQuery()->exists();

        return view('spike::components.usage-chart', [
            'hasUsage' => $hasUsage,
            'dailyUsageChartModel' => $hasUsage ? $this->getDailyUsageChartModel() : null,
        ]);
    }

    protected function getDailyUsageChartModel()
    {
        $colors = [
            config('spike.theme.color'),
            '#36A2EB',
            '#FFCE56',
            '#FF6384',
            '#4BC0C0',
            '#9966FF',
            '#C9CBCF',
            '#FF9F40',
            '#FFCD56',
            '#4BC0C0',
        ];

        $columnChartModel = LivewireCharts::columnChartModel()
            ->multiColumn()
            ->setColors($colors)
            ->setTitle(__('spike::translations.daily_credit_usage'));

        $creditUsageGroupedByType = $this->creditUsageGrouped();
        $defaultJsonConfig = $this->defaultChartJsonConfig();

        if ($creditUsageGroupedByType->count() > 1) {
            $columnChartModel = $columnChartModel
                ->stacked()
                ->legendPositionBottom()
                ->setJsonConfig(array_merge($defaultJsonConfig, [
                    'legend' => [
                        'offsetY' => 5,
                    ],
                    'grid' => [
                        'padding' => [
                            'bottom' => 10,
                            'top' => -30,
                        ],
                    ],
                ]));
        } else {
            $columnChartModel = $columnChartModel->withoutLegend()
                ->setJsonConfig(array_merge($defaultJsonConfig, [
                    //
                ]));
        }

        foreach ($creditUsageGroupedByType as $type => $creditUsageGrouped) {
            $creditType = CreditType::make($type);

            [$currentDay, $today] = $this->dateRange();

            while($currentDay->lte($today)) {
                /** @var Collection $creditUsageGrouped */
                $creditsUsed = $creditUsageGrouped->get($currentDay->toDateString());

                $columnChartModel->addSeriesColumn(
                    $creditType->name(),
                    $currentDay->toDateString(),
                    $creditsUsed ?? 0,
                );

                $currentDay = $currentDay->addDay();
            }
        }

        return $columnChartModel;
    }

    protected function creditUsageGrouped(): Collection
    {
        return $this->getUsageQuery()->get()
            ->groupBy(fn (CreditTransaction $tr) => $tr->credit_type->type)
            ->map(function (Collection $transactions) {
                return $transactions->groupBy(fn (CreditTransaction $tr) => $tr->created_at->toDateString())
                    ->map(fn (Collection $transactions) => abs($transactions->sum('credits') ?? 0));
            });
    }

    protected function getUsageQuery(): Builder
    {
        $dateRange = $this->dateRange();

        return CreditTransaction::query()
            ->whereBillable(Spike::resolve())
            ->onlyUsages()
            ->whereBetween('created_at', $dateRange);
    }

    protected function dateRange(): array
    {
        return [
            Carbon::today()->subMonth()->startOfDay(),
            Carbon::today()->endOfDay(),
        ];
    }

    protected function dateRangeLabels(): array
    {
        [$start, $end] = $this->dateRange();
        $labels = [];

        $currentDay = $start->copy();
        while($currentDay->lte($end)) {
            $labels[] = $currentDay->toDateString();
            $currentDay = $currentDay->addDay();
        }

        return $labels;
    }

    protected function defaultChartJsonConfig(): array
    {
        return [
            'grid' => [
                'padding' => [
                    'bottom' => 20,
                    'top' => 0,
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 0,
                ],
            ],
            'xaxis' => [
                'type' => 'datetime',
                'categories' => $this->dateRangeLabels(),
                'labels' => [
                    'rotate' => 90,
                ],
            ],
            'yaxis' => [
                'show' => false,
                'labels' => [
                    'show' => false,
                ],
            ],
        ];
    }
}
