import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  build: { outDir: 'dist', emptyOutDir: true },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:18100',
        changeOrigin: true,
      },
      '/connection': {
        target: 'http://127.0.0.1:18100',
        changeOrigin: true,
        ws: true,
      },
    },
  },
})
