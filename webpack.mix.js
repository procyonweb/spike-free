let mix = require('laravel-mix');

mix.copy('resources/images', 'resources/dist/images')
    .postCss('resources/css/app.css', 'resources/dist', [
        require('tailwindcss'),
    ]);
