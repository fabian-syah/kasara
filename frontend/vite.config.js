import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
  ],
  optimizeDeps: {
    include: ["chart.js", "vue-chartjs", "html-to-image"]
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor': ['vue', 'vue-router', 'pinia', 'axios'],
          'charts': ['chart.js', 'vue-chartjs'],
          'utils': ['lucide-vue-next', 'clsx', 'tailwind-merge'],
          'qr-scanner': ['html5-qrcode'],
          'export-tools': ['html-to-image', 'html2canvas', 'jspdf']
        }
      }
    },
    chunkSizeWarningLimit: 1000
  }
})
