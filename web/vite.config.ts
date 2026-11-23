/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// In development, /api requests are proxied to Laravel, so the browser only ever
// talks to one origin (no CORS) and never to Fake Store directly.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // xfwd adds X-Forwarded-For so Laravel rate-limits by the real client IP.
      '/api': { target: apiTarget, changeOrigin: true, xfwd: true },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
  },
})
