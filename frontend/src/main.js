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
// Global directive for currency masking that preserves cursor position and syncs data
app.directive('money', {
    mounted(el, binding) {
        const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
        if (!input) return;

        const format = (val) => {
            if (val === null || val === undefined || val === '') return '';
            const clean = val.toString().replace(/\D/g, '');
            if (!clean) return '';
            return parseInt(clean, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        };

        const sync = (numeric) => {
            if (typeof binding.value === 'function') {
                binding.value(numeric);
            } else if (binding.arg && binding.value && typeof binding.value === 'object') {
                binding.value[binding.arg] = numeric;
            } else if (Array.isArray(binding.value) && binding.value.length === 2 && typeof binding.value[0] === 'object') {
                binding.value[0][binding.value[1]] = numeric;
            }
        };

        // Initial format
        let initialVal = undefined;
        if (binding.arg && binding.value && typeof binding.value === 'object') {
            initialVal = binding.value[binding.arg];
        } else if (Array.isArray(binding.value) && binding.value.length === 2) {
            initialVal = binding.value[0][binding.value[1]];
        } else {
            initialVal = binding.value;
        }
        
        if (initialVal !== undefined) {
            input.value = format(initialVal);
        }

        input.addEventListener('input', (e) => {
            const originalValue = input.value;
            const selectionStart = input.selectionStart;
            
            // Count digits before the cursor to maintain position after formatting
            const digitsBeforeCursor = originalValue.slice(0, selectionStart).replace(/\D/g, '').length;
            
            // Clean and format
            const clean = originalValue.replace(/\D/g, '');
            if (!clean) {
                input.value = '';
                sync(0);
                return;
            }
            
            const numeric = parseInt(clean, 10);
            const formatted = numeric.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            
            input.value = formatted;
            
            // Update data source
            sync(numeric);
            
            // Restore cursor position
            let newPos = 0;
            let digitsFound = 0;
            while (digitsFound < digitsBeforeCursor && newPos < formatted.length) {
                if (/\d/.test(formatted[newPos])) digitsFound++;
                newPos++;
            }
            input.setSelectionRange(newPos, newPos);
        });
    },
    // Ensure UI stays in sync if data changes externally
    updated(el, binding) {
        const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
        if (!input) return;

        let newVal = undefined;
        if (binding.arg && binding.value && typeof binding.value === 'object') {
            newVal = binding.value[binding.arg];
        } else if (Array.isArray(binding.value) && binding.value.length === 2) {
            newVal = binding.value[0][binding.value[1]];
        } else {
            newVal = binding.value;
        }

        if (newVal === undefined) return;

        // Only update if the numeric value has actually changed to avoid cursor jitter
        const currentNumeric = parseInt(input.value.replace(/\D/g, ''), 10) || 0;
        if (currentNumeric !== newVal) {
            const clean = newVal.toString().replace(/\D/g, '');
            if (!clean) {
                input.value = '';
            } else {
                input.value = parseInt(clean, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        }
    }
});

app.config.errorHandler = (err, instance, info) => {
    console.error(`[Vue Error] ${info}:`, err)
}

app.mount('#app')