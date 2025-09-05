@props(['product'])
<div {{ $attributes }}>
    <p class="font-medium text-brand">{{ $product->name }}</p>
    @if(!empty($product->short_description))
        <p class="text-gray-600 text-sm">{{ $product->short_description }}</p>
    @endif
</div>
