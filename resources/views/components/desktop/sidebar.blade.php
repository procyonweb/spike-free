<div {{ $attributes->merge(['class' => 'hidden lg:flex lg:justify-end lg:w-1/3 lg:fixed lg:inset-y-0 lg:pt-12 lg:pb-10 text-white bg-brand']) }}">
    <div class="lg:flex lg:flex-col lg:items-end lg:mx-16 lg:max-w-md lg:w-full">
        <div class="h-0 flex-1 flex flex-col space-y-8 overflow-y-auto">
            {{ $slot }}
        </div>
    </div>
</div>
