import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            colors: {
                // Cinzas quentes do tom papel (20/11/2026): o 50 é o fundo de papel, o 100 o fundo suave,
                // o 200 o contorno, o 800 a tinta e o 900 o fundo escuro. Trocar a escala aqui muda o
                // site inteiro de uma vez, sem mexer nas classes gray-* de cada tela.
                gray: {
                    50: '#f8f6f1',
                    100: '#efebe3',
                    200: '#e4ded2',
                    300: '#d3ccbe',
                    400: '#a8a297',
                    500: '#78726a',
                    600: '#5a554d',
                    700: '#47433c',
                    800: '#2b2925',
                    900: '#1b1a17',
                    950: '#12110f',
                },
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                chart: {
                    1: 'hsl(var(--chart-1))',
                    2: 'hsl(var(--chart-2))',
                    3: 'hsl(var(--chart-3))',
                    4: 'hsl(var(--chart-4))',
                    5: 'hsl(var(--chart-5))',
                },
                // Família de botões e avisos (tom papel). Ver docs/design/componentes.html.
                brand: {
                    DEFAULT: 'hsl(var(--primary))',
                    ink: 'hsl(var(--primary-foreground))',
                    edge: 'var(--mz-brand-edge)',
                    hover: 'var(--mz-brand-hover)',
                    soft: 'var(--mz-brand-soft)',
                    soft2: 'var(--mz-brand-soft2)',
                    'soft-ink': 'var(--mz-brand-soft-ink)',
                    mark: 'var(--mz-brand-mark)',
                },
                danger: {
                    DEFAULT: 'hsl(var(--destructive))',
                    ink: 'hsl(var(--destructive-foreground))',
                    edge: 'var(--mz-danger-edge)',
                    hover: 'var(--mz-danger-hover)',
                    soft: 'var(--mz-danger-soft)',
                    soft2: 'var(--mz-danger-soft2)',
                    'soft-ink': 'var(--mz-danger-soft-ink)',
                },
                choice: {
                    DEFAULT: 'var(--mz-choice)',
                    soft: 'var(--mz-choice-soft)',
                    ink: 'var(--mz-choice-ink)',
                },
                right: {
                    DEFAULT: 'var(--mz-ok)',
                    soft: 'var(--mz-ok-soft)',
                    ink: 'var(--mz-ok-ink)',
                },
                wrong: {
                    DEFAULT: 'var(--mz-bad)',
                    soft: 'var(--mz-bad-soft)',
                    ink: 'var(--mz-bad-ink)',
                },
                paper: {
                    DEFAULT: 'hsl(var(--background))',
                    face: 'hsl(var(--card))',
                    ink: 'hsl(var(--foreground))',
                    ink2: 'var(--mz-ink2)',
                    muted: 'hsl(var(--muted-foreground))',
                    line: 'hsl(var(--border))',
                    edge: 'var(--mz-edge)',
                    tint: 'hsl(var(--secondary))',
                    tint2: 'var(--mz-tint2)',
                    off: 'var(--mz-off)',
                    'off-ink': 'var(--mz-off-ink)',
                    'off-edge': 'var(--mz-off-edge)',
                },
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar-background))',
                    foreground: 'hsl(var(--sidebar-foreground))',
                    primary: 'hsl(var(--sidebar-primary))',
                    'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
                    accent: 'hsl(var(--sidebar-accent))',
                    'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                    border: 'hsl(var(--sidebar-border))',
                    ring: 'hsl(var(--sidebar-ring))',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
