/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],

    theme: {
        extend: {
            // ── MD3 Color Tokens ─────────────────────────────────────────
            colors: {
                'primary':                    '#002444',
                'primary-container':          '#1b3a5c',
                'primary-fixed':              '#d2e4ff',
                'primary-fixed-dim':          '#abc9f2',
                'on-primary':                 '#ffffff',
                'on-primary-fixed':           '#001c38',
                'on-primary-fixed-variant':   '#2b486b',
                'on-primary-container':       '#87a4cc',
                'inverse-primary':            '#abc9f2',

                'secondary':                  '#006c4e',
                'secondary-container':        '#83f5c6',
                'secondary-fixed':            '#86f8c9',
                'secondary-fixed-dim':        '#68dbae',
                'on-secondary':               '#ffffff',
                'on-secondary-container':     '#007151',
                'on-secondary-fixed':         '#002115',
                'on-secondary-fixed-variant': '#00513a',

                'tertiary':                   '#755b00',
                'tertiary-container':         '#c9a84c',
                'tertiary-fixed':             '#ffe08f',
                'tertiary-fixed-dim':         '#e6c364',
                'on-tertiary':                '#ffffff',
                'on-tertiary-container':      '#503d00',
                'on-tertiary-fixed':          '#241a00',
                'on-tertiary-fixed-variant':  '#584400',

                'surface':                    '#f8f9ff',
                'surface-dim':                '#cbdbf5',
                'surface-bright':             '#f8f9ff',
                'surface-container-lowest':   '#ffffff',
                'surface-container-low':      '#eff4ff',
                'surface-container':          '#e5eeff',
                'surface-container-high':     '#dce9ff',
                'surface-container-highest':  '#d3e4fe',
                'surface-variant':            '#d3e4fe',
                'surface-tint':               '#436084',
                'on-surface':                 '#0b1c30',
                'on-surface-variant':         '#43474e',
                'inverse-surface':            '#213145',
                'inverse-on-surface':         '#eaf1ff',

                'background':                 '#f8f9ff',
                'on-background':              '#0b1c30',
                'outline':                    '#73777f',
                'outline-variant':            '#c3c6cf',
                'error':                      '#ba1a1a',
                'error-container':            '#ffdad6',
                'on-error':                   '#ffffff',
                'on-error-container':         '#93000a',
            },

            // ── Typography ───────────────────────────────────────────────
            fontFamily: {
                'arabic':       ['Tajawal', 'IBM Plex Sans', 'sans-serif'],
                'data-tabular': ['IBM Plex Sans', 'sans-serif'],
            },

            // Tajawal steps 400 → 500 → 700; there is no 600. Left alone the
            // browser rounds `font-semibold` up to 700, and the dashboard uses
            // it 155 times — almost all on text-xs and text-[10px] labels,
            // badges and table headings, where 700 turns Arabic into a smudge.
            // Pointing the utility at 500 keeps those readable and leaves
            // `font-bold` as the only heavy step, so the hierarchy survives.
            fontWeight: {
                'semibold': '500',
            },

            fontSize: {
                'display-lg':  ['36px', { lineHeight: '44px', fontWeight: '700' }],
                // 700 rather than 600 here: at 28px and 22px a heading wants the
                // weight, and Tajawal has nothing between 500 and 700.
                'headline-lg': ['28px', { lineHeight: '36px', fontWeight: '700' }],
                'headline-md': ['22px', { lineHeight: '30px', fontWeight: '700' }],
                'body-lg':     ['18px', { lineHeight: '28px', fontWeight: '400' }],
                'body-md':     ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'body-sm':     ['14px', { lineHeight: '20px', fontWeight: '400' }],
                // A 14px tracked-out label reads better at 500 than at 700.
                'label-md':    ['14px', { lineHeight: '16px', letterSpacing: '0.02em', fontWeight: '500' }],
            },

            // ── Spacing ──────────────────────────────────────────────────
            spacing: {
                'xs':           '4px',
                'sm':           '8px',
                'md':           '16px',
                'lg':           '24px',
                'xl':           '32px',
                'gutter':       '20px',
                'sidebar-width':'280px',
            },

            // ── Border Radius ────────────────────────────────────────────
            borderRadius: {
                DEFAULT: '0.125rem',
                'lg':    '0.25rem',
                'xl':    '0.5rem',
                'full':  '0.75rem',
            },
        },
    },

    plugins: [],
};
