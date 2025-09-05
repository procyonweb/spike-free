<?php

use Asantibanez\LivewireCharts\Models\ColumnChartModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\View\Components\UsageChart;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createBillable();
    Spike::resolve(fn () => $this->user);
});

test('usage view contains no usage by default', closure: function () {
    tap((new UsageChart())->render(), function (View $view) {
        $view->assertViewIs('spike::components.usage-chart');
        $view->assertViewHas('hasUsage', false);
        $view->assertViewHas('dailyUsageChartModel', null);
    });
});

test('usage view contains daily usage data', function () {
    $this->user->credits()->add(500);
    $firstDay = now();
    $this->user->credits()->spend(50);
    testTime()->addDay();
    $secondDay = now();
    $this->user->credits()->spend(100);

    tap((new UsageChart())->render(), function (View $view) use ($firstDay, $secondDay) {
        $view->assertViewHas('hasUsage', true)
            ->assertViewHas('dailyUsageChartModel', function (ColumnChartModel $model) use ($firstDay, $secondDay) {
                /** @var Collection $modelData */
                $modelData = (fn() => $this->data)->call($model);

                return $modelData->get('credits')->contains(function (array $item) use ($firstDay) {
                        return $item['title'] === $firstDay->toDateString()
                            && $item['value'] === 50;
                    })
                    && $modelData->get('credits')->contains(function (array $item) use ($secondDay) {
                        return $item['title'] === $secondDay->toDateString()
                            && $item['value'] === 100;
                    });
            });
    });
});

test('usage view groups multiple usages on the same day', function () {
    $this->user->credits()->add(500);
    $this->user->credits()->spend(100);
    $this->user->credits()->currentUsageTransaction()->expire();
    $this->user->credits()->spend(60);
    expect($this->user->credits()->balance())->toBe(500 - 160);

    tap((new UsageChart())->render(), function (View $view) {
        $view->assertViewHas('hasUsage', true)
            ->assertViewHas('dailyUsageChartModel', function (ColumnChartModel $model) {
                /** @var Collection $modelData */
                $modelData = (fn() => $this->data)->call($model);

                return $modelData->get('credits')->contains(function (array $item) {
                        return $item['title'] === now()->toDateString()
                            && $item['value'] === 160;
                    });
            });
    });
});

test('usage view can show multiple types of credits', function () {
    $this->user = createBillable();
    $this->user->credits()->add(500);
    $this->user->credits()->type('sms')->add(400);
    $this->user->credits()->spend(100);
    $this->user->credits()->type('sms')->spend(80);

    tap((new UsageChart())->render(), function (View $view) {
        $view->assertViewHas('hasUsage', true)
            ->assertViewHas('dailyUsageChartModel', function (ColumnChartModel $model) {
                /** @var Collection $modelData */
                $modelData = (fn() => $this->data)->call($model);

                return $modelData->get('credits')->contains(function (array $item) {
                        return $item['title'] === now()->toDateString()
                            && $item['value'] === 100;
                    })
                    && $modelData->get('sms')->contains(function (array $item) {
                        return $item['title'] === now()->toDateString()
                            && $item['value'] === 80;
                    });
            });
    });
});
