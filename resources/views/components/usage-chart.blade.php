<div {{ $attributes->merge(['style' => 'height: 160px; ']) }}>
    @if($hasUsage && $dailyUsageChartModel)
    <livewire:livewire-column-chart :column-chart-model="$dailyUsageChartModel" />
    @else
    <div class="w-full h-full flex items-center justify-center text-gray-600">
        {{ __('spike::translations.daily_credit_usage_empty') }}
    </div>
    @endif
</div>
