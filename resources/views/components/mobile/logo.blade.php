@if($logoUrl = config('spike.theme.logo_url'))
<div class="flex-shrink-0 flex items-center justify-center px-4">
    <img class="h-10 w-auto" src="{{ $logoUrl }}" alt="{{ config('app.name') }} logo">
</div>
@endif
