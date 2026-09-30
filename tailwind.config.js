import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                wordmark: ['Montserrat', 'Arial Black', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    green: '#038601',
                    orange: '#E8692A',
                    yellow: '#FFD900',
                    300: '#FFE670',
                    200: '#FFF3B9',
                    100: '#FFF6CC',
                },
            },
        },
    },

    plugins: [forms],
};
