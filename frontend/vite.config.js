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
          'qr-scanner': ['html5-qrcode'],
          'export-tools-v2': ['html-to-image', 'html2canvas', 'jspdf']
        }
      }
    },
    chunkSizeWarningLimit: 1000,

    // --- CSP Compatibility ---
    // 1. Disable modulepreload polyfill to prevent inline script injection.
    //    Modern browsers support modulepreload natively.
    modulePreload: {
      polyfill: false
    },
    // 2. Keep CSS in external files (default behavior, explicit for clarity).
    //    This avoids inline <style> tags that would need nonces.
    cssCodeSplit: true,
    // 3. Prevent small assets from being inlined as data URIs in JS.
    //    All resources stay as external files, loadable under strict CSP.
    assetsInlineLimit: 0,
  },

  // CSP Library Compatibility Notes:
  // - Chart.js v4: Declarative config objects, no eval()/new Function(). Safe under
  //   strict CSP without 'unsafe-eval'.
  // - html5-qrcode: Uses Canvas/WebRTC (getUserMedia). Requires 'blob:' in img-src
  //   and camera permission via Permissions-Policy.
  // - jspdf/html2canvas: Use Canvas API and blob: URLs for downloads. Require 'blob:'
  //   in img-src and connect-src.
  // - Tesseract.js: Uses Web Workers from external files. Compatible with
  //   worker-src 'self' blob:.
  //
  // No eval() or new Function() usage exists in application source code.
  // CSP is deployed as Content-Security-Policy-Report-Only for safe monitoring.
  //
  // Nonce injection: The backend (Laravel) serves index.html and can inject the
  // nonce attribute on the <script type="module"> tag at response time. No Vite
  // plugin is needed for nonce injection since the SPA is served through Laravel.
})
