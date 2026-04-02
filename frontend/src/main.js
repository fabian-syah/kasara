import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import axios from 'axios' // TAMBAHKAN INI
import './style.css'

// Konfigurasi Dasar Axios
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || 'https://api.stokps.com/api';
axios.defaults.withCredentials = true; // Penting untuk Sanctum

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Global error handler - prevents unhandled errors from crashing the entire app
// Global directive for currency masking that preserves cursor position
app.directive('money', {
    mounted(el) {
        const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
        if (!input) return;

        input.addEventListener('input', (e) => {
            const originalValue = input.value;
            const selectionStart = input.selectionStart;
            
            // Count digits before the cursor
            const digitsBeforeCursor = originalValue.slice(0, selectionStart).replace(/\D/g, '').length;
            
            // Clean and format
            const clean = originalValue.replace(/\D/g, '');
            if (!clean) {
                input.value = '';
                return;
            }
            
            const numeric = parseInt(clean, 10);
            const formatted = numeric.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            
            input.value = formatted;
            
            // Restore cursor position based on digit count
            let newPos = 0;
            let digitsFound = 0;
            while (digitsFound < digitsBeforeCursor && newPos < formatted.length) {
                if (/\d/.test(formatted[newPos])) {
                    digitsFound++;
                }
                newPos++;
            }
            
            // Ensure cursor isn't placed BEFORE a leading dot or similar artifact (though dots are trailing in regex)
            input.setSelectionRange(newPos, newPos);
        });
    }
});

app.config.errorHandler = (err, instance, info) => {
    console.error(`[Vue Error] ${info}:`, err)
}

app.mount('#app')