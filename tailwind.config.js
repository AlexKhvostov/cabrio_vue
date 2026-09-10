/** @type {import('tailwindcss').Config} */
// Единый источник правды по стилю приложения "CabrioRide — Открытая дорога".
// Меняем цвет/шрифт/размер здесь — он обновляется сразу во всех компонентах и экранах.
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        bg: '#161a2e',
        surface: '#1f2440',
        surface2: '#262c4d',
        accent: '#ff8a5c',
        accent2: '#5ec8d8',
        text: '#f4f2ee',
        muted: '#9aa0c3',
      },
      fontFamily: {
        sans: ['Nunito', 'Manrope', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        xl2: '20px',
      },
      fontSize: {
        // компактная типографическая шкала, зафиксированная для всего приложения
        micro: ['9px', '1.2'],
        label: ['10px', '1.2'],
        meta: ['11px', '1.3'],
        body: ['12px', '1.4'],
        title: ['13px', '1.3'],
        name: ['14px', '1.2'],
      },
    },
  },
  plugins: [],
}
