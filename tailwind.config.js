/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      fontFamily: {
        'vazir': ['Vazir', 'sans-serif'],
        'inter': ['Inter', 'sans-serif'],
      },
      colors: {
        gold: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
  // Enable RTL support
  corePlugins: {
    // Disable some plugins that don't work well with RTL
    textAlign: true,
  },
  // Add RTL utilities
  variants: {
    extend: {
      textAlign: ['rtl'],
      float: ['rtl'],
      margin: ['rtl'],
      padding: ['rtl'],
    },
  },
}