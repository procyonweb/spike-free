@props(['plan'])
<div {{ $attributes }}>
    <p class="font-medium text-brand">{{ $plan->name }}</p>
    @if(!empty($plan->short_description))
        <p class="text-gray-600 text-sm">{{ $plan->short_description }}</p>
    @endif
</div>
