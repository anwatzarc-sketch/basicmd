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
  // THE day/night enabler.
  //
  // Tailwind defaults to darkMode: 'media', where every `dark:` utility keys
  // off the operating system's prefers-color-scheme and nothing else. Under
  // that default the theme toggle flips the `.dark` class on <html> and the
  // page does not change - only the hand-written `:root` / `.dark` CSS
  // variables respond, so the background shifts while every `dark:` utility
  // in the templates stays stubbornly light.
  //
  // 'class' makes `dark:` respond to the `.dark` class instead, which is what
  // the toggle actually sets.
  darkMode: 'class',

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

      // Every shade below is `rgb(var(--brand-N) / <alpha-value>)` rather
      // than a literal hex. `--brand-N` is defined on :root by
      // partials/brand-vars (see resources/css/app.css), with today's exact
      // hex values as the default - CompanyBrand.json overrides them at
      // runtime without a rebuild. `<alpha-value>` is Tailwind's own
      // placeholder token: it lets `bg-medical-700/50` keep working, by
      // substituting the alpha straight into the rgb() function.
      colors: {
        medical: {
          50:  'rgb(var(--brand-50) / <alpha-value>)',
          100: 'rgb(var(--brand-100) / <alpha-value>)',
          200: 'rgb(var(--brand-200) / <alpha-value>)',
          300: 'rgb(var(--brand-300) / <alpha-value>)',
          400: 'rgb(var(--brand-400) / <alpha-value>)',
          500: 'rgb(var(--brand-500) / <alpha-value>)',
          600: 'rgb(var(--brand-600) / <alpha-value>)',
          700: 'rgb(var(--brand-700) / <alpha-value>)',
          800: 'rgb(var(--brand-800) / <alpha-value>)',
          900: 'rgb(var(--brand-900) / <alpha-value>)',
          950: 'rgb(var(--brand-950) / <alpha-value>)',
        },
        gold: 'rgb(var(--brand-secondary) / <alpha-value>)',
      },

      fontSize: {
        // Ge'ez glyphs are visually larger at the same point size and need
        // more leading than Latin text to avoid collision.
        'ethiopic-base': ['1rem', { lineHeight: '1.85' }],
        'ethiopic-lg':   ['1.125rem', { lineHeight: '1.8' }],

        // Sits between text-3xl and text-4xl. Used by .stat-value for the big
        // dashboard figures; without it the build fails on an unknown class.
        '3.5xl': ['2.0625rem', { lineHeight: '2.375rem' }],
      },

      spacing: {
        // Steps app.css uses that are not in Tailwind's default scale. Without
        // these the build fails outright (`The h-13 class does not exist`), so
        // they are added rather than rounding the design to the nearest
        // existing step.
        //
        //   4.5  -> .glass-input / .field padding
        //   5.5  -> .package-card padding
        //   13   -> .service-icon box
        '4.5': '1.125rem',
        '5.5': '1.375rem',
        '13': '3.25rem',
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
        // The typography plugin writes these custom properties out as plain
        // colour values, not through Tailwind's own utility pipeline, so
        // they cannot take the `<alpha-value>` placeholder the way
        // `colors.medical` does above - `rgb(var(--brand-900))` (no
        // placeholder) is the literal value that plugin expects.
        DEFAULT: {
          css: {
            '--tw-prose-body': theme('colors.slate[600]'),
            '--tw-prose-headings': 'rgb(var(--brand-900))',
            '--tw-prose-links': 'rgb(var(--brand-700))',
            '--tw-prose-bold': 'rgb(var(--brand-900))',
            '--tw-prose-quotes': 'rgb(var(--brand-800))',
            '--tw-prose-quote-borders': 'rgb(var(--brand-200))',
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
