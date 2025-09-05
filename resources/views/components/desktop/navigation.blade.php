<div class="space-y-2">
    @foreach($navLinks as $link)
        <x-spike::desktop.nav-link
            :route-name="$link['route_name']"
            :icon="$link['icon']"
            :needs-attention="$link['needs_attention'] ?? false"
        >{{ $link['label'] }}</x-spike::desktop.nav-link>
    @endforeach
</div>
