@props(['feature'])
<div {{ $attributes->merge(['class' => 'flex items-center text-sm text-gray-600']) }}>
    <x-spike::icons.checkmark-circle
        class="flex-shrink-0 mr-1.5 size-5 opacity-75"/>
    <p>{!! $feature !!}</p>
</div>
