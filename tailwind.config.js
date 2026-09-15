/**
 * Tailwind configuration for Aster Medical Center.
 *
 * The `medical` palette is carried over verbatim from the approved prototype
 * so the production build is visually identical to the design that was signed
 * off, while gaining the purged-CSS and self-hosted-font benefits of a real
 * build step.
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
  // Every file that can emit a class name. Missing one here means the class
  // is purged out of the production bundle and the page silently loses its
  // styling, so the PHP sources are included alongside the templates.
  content: [
    './resources/views/**/*.php',
    './src/**/*.php',
    './public/assets/js/**/*.js',
    './lang/*.php',
  ],

  // Safelist classes that only ever appear as computed strings - the badge
  // and chip helpers on the enums build them with match(), which the Tailwind
  // scanner cannot follow.
  safelist: [
    { pattern: /^bg-(amber|teal|emerald|rose|slate|sky|violet|medical|zinc)-(50|100|200)$/ },
    { pattern: /^text-(amber|teal|emerald|rose|slate|sky|violet|medical|zinc)-(600|700|800)$/ },
    { pattern: /^border-(amber|teal|emerald|rose|slate|sky|violet|medical|zinc)-(100|200|300)$/ },
    { pattern: /^bg-(amber|teal|emerald|rose|slate)-(400|500)$/ },
  ],

  theme: {
    extend: {
      fontFamily: {
        // Inter first for Latin; Noto Sans Ethiopic supplies the Ge'ez
        // glyphs Inter has no coverage for, so both are always present.
        sans: [
          'Inter',
          'Noto Sans Ethiopic',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'sans-serif',
        ],
        // Applied to <body> when the locale is Amharic: Ethiopic leads so the
        // browser does not have to fall through Inter on every glyph.
        ethiopic: [
          'Noto Sans Ethiopic',
          'Nyala',
          'Abyssinica SIL',
          'Inter',
          'sans-serif',
        ],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },

      colors: {
        medical: {
          50:  '#effcfb',
          100: '#d8f7f4',
          200: '#b3ede8',
          300: '#82ded7',
          400: '#4bc4bd',
          500: '#0f8f89',
          600: '#087b77',
          700: '#056460',
          800: '#0c4a47',
          900: '#063b3a',
          950: '#032423',
        },
        gold: '#d99a32',
      },

      fontSize: {
        // Ge'ez glyphs are visually larger at the same point size and need
        // more leading than Latin text to avoid collision.
        'ethiopic-base': ['1rem', { lineHeight: '1.85' }],
        'ethiopic-lg':   ['1.125rem', { lineHeight: '1.8' }],
      },

      borderRadius: {
        '4xl': '2rem',
      },

      boxShadow: {
        card: '0 1px 3px rgba(15, 23, 42, .06), 0 1px 2px rgba(15, 23, 42, .04)',
        lift: '0 20px 45px rgba(6, 59, 58, .10)',
        brand: '0 10px 30px rgba(5, 100, 96, .20)',
      },

      keyframes: {
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%':      { transform: 'translateY(-8px)' },
        },
        'fade-up': {
          '0%':   { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },

      animation: {
        float: 'float 5s ease-in-out infinite',
        'fade-up': 'fade-up .35s ease-out both',
      },

      typography: (theme) => ({
        DEFAULT: {
          css: {
            '--tw-prose-body': theme('colors.slate[600]'),
            '--tw-prose-headings': theme('colors.medical[900]'),
            '--tw-prose-links': theme('colors.medical[700]'),
            '--tw-prose-bold': theme('colors.medical[900]'),
            '--tw-prose-quotes': theme('colors.medical[800]'),
            '--tw-prose-quote-borders': theme('colors.medical[200]'),
            maxWidth: '68ch',
            lineHeight: '1.75',
          },
        },
        // Looser measure and leading for Amharic article bodies.
        ethiopic: {
          css: {
            lineHeight: '1.9',
            maxWidth: '62ch',
          },
        },
      }),
    },
  },

  plugins: [
    require('@tailwindcss/forms')({ strategy: 'class' }),
    require('@tailwindcss/typography'),
  ],
};
