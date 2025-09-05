<x-spike::layout>
    <x-slot:title>
        {{ __('spike::translations.usage') }}
    </x-slot:title>

    <style>
        .apexcharts-tooltip-text-label {
            display: none !important;
        }
    </style>

    <div class="bg-white shadow lg:rounded-md">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.daily_credit_usage') }}</h3>
            <x-spike::usage-chart class="mt-5" />
        </div>
    </div>

    <div class="mt-12">
        <livewire:spike::credit-transactions />
    </div>
</x-spike::layout>
