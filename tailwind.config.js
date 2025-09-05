module.exports = {
    purge: {
        content: [
            './vendor/wire-elements/modal/resources/views/*.blade.php',
            './resources/css/*.css',
            './resources/js/*.js',
            './resources/js/**/*.js',
            './resources/js/*.vue',
            './resources/js/**/*.vue',
            './resources/views/*.blade.php',
            './resources/views/**/*.blade.php',
        ],
        options: {
            safelist: [
                'sm:max-w-2xl',
                'sm:w-full',
                'sm:max-w-md',
                'md:max-w-xl',
                'lg:max-w-2xl',
            ],
        },
    },
    theme: {
        extend: {
            flex: {
                '2': '2 2 0%',
            },
            colors: {
                brand: 'var(--brand-color)',
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
