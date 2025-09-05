<div class="relative z-40 lg:hidden" role="dialog" aria-modal="true" style="display: none;" x-show="mobileMenuOpen">
    <div class="fixed inset-0 bg-gray-600 bg-opacity-75"
         x-show="mobileMenuOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
    ></div>

    <div class="fixed inset-0 flex z-40" style="display: none;" x-show="mobileMenuOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
    >
        <div class="relative flex-1 flex flex-col max-w-xs w-full pt-10 pb-4 bg-brand text-white"
             @click.outside="mobileMenuOpen = false"
        >
            <div class="absolute top-0 right-0 -mr-12 pt-2"
                 x-show="mobileMenuOpen"
                 x-transition:enter="ease-in-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in-out duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
            >
                <button type="button" @click="mobileMenuOpen = false"
                        class="ml-1 flex items-center justify-center size-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                    <span class="sr-only">{{ __('spike::translations.close_sidebar') }}</span>
                    <x-spike::icons.dismiss class="size-6 text-white" />
                </button>
            </div>

            {{ $slot }}

        </div>

        <div class="flex-shrink-0 w-14" aria-hidden="true">
            <!-- Dummy element to force sidebar to shrink to fit close icon -->
        </div>
    </div>
</div>
