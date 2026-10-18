import { defineConfig, loadEnv } from 'vite'
import type { Plugin, ViteDevServer } from 'vite'
import react from '@vitejs/plugin-react'

function reloadOnSourceChanges(): Plugin {
  let server: ViteDevServer | undefined
  const watchedDirectories = ['src', '../app', '../config', '../bin', '../database', '../public/index.php']
  return {
    name: 'giftcheta-source-full-reload',
    configureServer(devServer) {
      server = devServer
      server.watcher.add(watchedDirectories)
      server.watcher.on('all', (event, file) => {
        if (!['change', 'add', 'unlink'].includes(event)) return
        const changedFile = file.replaceAll('\\', '/').toLowerCase()
        const isSourceFile = ['/src/', '/app/', '/config/', '/bin/', '/database/', '/public/index.php'].some(directory => changedFile.includes(directory))
        const isDependencyOrBuildOutput = ['/vendor/', '/node_modules/', '/public/admin/'].some(directory => changedFile.includes(directory))
        if (isSourceFile && !isDependencyOrBuildOutput) server?.ws.send({ type: 'full-reload' })
      })
    },
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', '')
  return {
    plugins: [react(), reloadOnSourceChanges()],
    base: '/admin/',
    build: { outDir: '../public/admin', emptyOutDir: true },
    server: {
      host: '0.0.0.0',
      watch: { usePolling: true, interval: 250 },
      proxy: { '/api': env.API_PROXY_TARGET || 'http://localhost:8080' },
    },
  }
})
