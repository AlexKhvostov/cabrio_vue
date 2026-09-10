import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ command }) => ({
  // На сервере приложение живёт по адресу vue.cabrioride.by/app/,
  // поэтому в собранной версии все пути должны быть относительно /app/.
  // На локальной разработке (npm run dev) путь остаётся корневым.
  base: command === 'build' ? '/app/' : '/',
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
  },
}))
