/** Tailwind build config. Merges the palettes used by the site pages and the claims form. */
module.exports = {
  content: [
    './*.html', './*.php', './*/index.html',
    './src/**/*.html', './scripts/**/*.js', './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        navy:  { DEFAULT: '#0D1B3E', deep: '#0D1B3E', mid: '#1A2F5C', light: '#2A4A8A' },
        brand: { DEFAULT: '#4DA6D6', dark: '#2A7BAA', light: '#B3DFF0', pale: '#EBF7FD' },
        sky:   '#7EC8E3',
      },
      fontFamily: { sans: ['Poppins', 'sans-serif'] },
    },
  },
};
