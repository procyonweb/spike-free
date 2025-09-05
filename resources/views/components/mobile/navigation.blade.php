<div class="space-y-2">
    @foreach($navLinks as $link)
        <x-spike::mobile.nav-link
            :route-name="$link['route_name']"
            :icon="$link['icon']"
            :needs-attention="$link['needs_attention'] ?? false"
        >{{ $link['label'] }}</x-spike::mobile.nav-link>
    @endforeach
</div>
