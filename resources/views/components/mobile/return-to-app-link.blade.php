@if($url = config('spike.return_url'))
    <div class="my-10 px-3">
        <a href="{{ $url }}" class="flex items-center text-sm opacity-75 hover:opacity-100">
            <x-spike::icons.arrow-left class="size-3 inline mr-2" />
            <span class="underline">{{ __('spike::translations.return_to_app', ['app_name' => config('app.name')]) }}</span>
        </a>
    </div>
@endif
