import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', '')
  return {
    plugins: [react()],
    base: '/admin/',
    build: { outDir: '../public/admin', emptyOutDir: true },
    server: {
      host: '0.0.0.0',
      watch: { usePolling: true, interval: 250 },
      proxy: { '/api': env.API_PROXY_TARGET || 'http://localhost:8080' },
    },
  }
})
