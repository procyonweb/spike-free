<!doctype html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-gray-100" style="--brand-color: {{ $themeColor }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@isset($title){{ $title }} - @endisset{{ config('app.name') }}</title>

    <style>[x-cloak] { display: none !important; }</style>
    @if($faviconUrl = config('spike.theme.favicon_url'))<link rel="icon" href="{{ $faviconUrl }}">@endif
    <link rel="stylesheet" href="{{ asset('vendor/spike/app.css') }}">
    @livewireStyles
    @spikeJS
</head>
<body class="h-full" style="--brand-color: {{ $themeColor }};">
    <div class="min-h-full" x-data="{ mobileMenuOpen: false }">
        @if($hasIncompleteSubscriptionPayment)
        <div class="sm:fixed inset-x-0 top-0 px-5 py-2 bg-orange-300 text-orange-900 text-center text-sm">
            {!! trans('spike::translations.incomplete_payment_banner', ['click_here' => '<a href="' . route('spike.subscribe.incomplete-payment') . '" class="font-bold hover:underline">Complete the payment</a>']) !!}
        </div>
        @endif

        <x-spike::mobile.slideout-menu>
            <x-spike::mobile.logo />

            <div class="mt-8 flex-1 h-0 overflow-y-auto">
                <nav class="px-5">
                    <x-spike::mobile.navigation :nav-links="$navLinks" />
                    <x-spike::mobile.return-to-app-link />
                </nav>
            </div>
        </x-spike::mobile.slideout-menu>

        <!-- Static sidebar for desktop -->
        <x-spike::desktop.sidebar :class="$hasIncompleteSubscriptionPayment ? 'sm:mt-9' : ''">
            <x-spike::desktop.logo />

            <x-spike::desktop.user-info
                :billable="$billable"
                :credit-balance="$creditBalance"
                :display-avatar="$displayAvatar"
                :avatar-url="$avatarUrl"
            />

            <nav>
                <x-spike::desktop.navigation :nav-links="$navLinks" />
                <x-spike::desktop.return-to-app-link />
            </nav>
        </x-spike::desktop.sidebar>

        <div class="lg:flex @if($hasIncompleteSubscriptionPayment) sm:mt-9 @endif">
            <!-- fake column to drive the main content 1/3 to the right -->
            <div class="hidden lg:block lg:w-1/3"></div>

            <!-- Main column -->
            <div class="lg:w-2/3 flex flex-col bg-gray-100 min-h-screen">
                <x-spike::mobile.navbar>
                    <x-spike::mobile.menu-button />
                    <x-spike::mobile.user-info
                        :billable="$billable"
                        :credit-balance="$creditBalance"
                        :display-avatar="$displayAvatar"
                        :avatar-url="$avatarUrl"
                    />
                </x-spike::mobile.navbar>

                <main class="flex-1 lg:max-w-4xl w-full lg:px-10 pt-4 lg:pt-8 lg:pb-10">
                    <x-spike::shared.page-title :title="$title" />

                    <div class="pt-8 pb-16 lg:px-8 lg:pb-20">
                        <!-- main content of the page -->
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </div>

@livewireScripts
@livewire('livewire-ui-modal')
@livewireChartsScripts
@stack('scripts')
</body>
</html>
