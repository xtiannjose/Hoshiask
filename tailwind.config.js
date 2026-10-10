import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Legacy name kept so existing classes keep working: this scale is now the brand navy (700 = hoshishi-primary).
                'hoshishi-blue': {
                    50: '#f3f6fb',
                    100: '#e4eaf4',
                    200: '#c9d4e8',
                    300: '#9fb0d0',
                    400: '#7288b3',
                    500: '#4f6694',
                    600: '#3a4f7c',
                    700: '#2c3d63',
                    800: '#21304f',
                    900: '#1a2640',
                },
                'hoshishi-primary': '#2c3d63',
                'hoshishi-primary-dark': '#21304f',
                'hoshishi-dark': '#1a2640',
                // Amber darkened from the logo's golden fur so white text on it stays at 5:1.
                'hoshishi-accent': '#b45309',
                'hoshishi-accent-dark': '#92400e',
                ink: '#0f172a',
                mist: '#f8fafc',
                spark: '#e0a050',
            },
            boxShadow: {
                glow: '0 20px 45px -28px rgba(26, 38, 64, 0.45)',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-6px)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.5s ease-out both',
                float: 'float 5s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
