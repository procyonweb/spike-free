@php $isCurrent = request()->routeIs($routeName); @endphp
<a href="{{ route($routeName) }}"
   class="group flex items-center px-3 py-2.5 text-base leading-5 font-medium rounded-md {{ $isCurrent ? 'bg-white bg-opacity-10' : 'hover:bg-white hover:bg-opacity-25' }}"
   {{ $isCurrent ? 'aria-current="page"' : '' }}>
    <x-dynamic-component :component="$icon" class="mr-3 flex-shrink-0 size-6" />
    {{ $slot }}
    @if($needsAttention)
        <x-spike::icons.warning class="ml-auto size-5 text-orange-400" />
    @endif
</a>
