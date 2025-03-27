import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                Sowjetschablone: ['Sowjetschablone', ...defaultTheme.fontFamily.sans],
                Typingrad: ['Typingrad', ...defaultTheme.fontFamily.sans],
                FIFARussia2018: ['FIFARussia2018', ...defaultTheme.fontFamily.sans],
                RubikMedium: ['Rubik-Medium', ...defaultTheme.fontFamily.sans],
                ComfortaaVariableFont: ['Comfortaa-VariableFont_wght', ...defaultTheme.fontFamily.sans],
                OrelegaOneRegular: ['OrelegaOne-Regular', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, typography],
};
